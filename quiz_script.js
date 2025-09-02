// quiz_script.js - Unified Quiz Management + Safe Student Rendering (fixed)
// - Autosave scoped per quiz (uses localStorage key "quizProgress:<QUIZ_ID>")
// - Clears autosave on submit
// - MCQ radios submit numeric index values to match DB ("0","1",...)
// - Renders FillBlank text input
// - Keeps timer, nav (if you use pills), and teacher builder

document.addEventListener('DOMContentLoaded', function () {
  initQuizFunctionality();
});

function initQuizFunctionality() {
  initQuestionManagement();   // no-op if #questions doesn't exist
  initQuizTimer();
  initQuizSubmission();
}

/* ===========================
   Teacher: Question Management
=========================== */
function initQuestionManagement() {
  const questionsContainer = document.getElementById('questions');
  if (!questionsContainer) return;

  let questionCount = questionsContainer.querySelectorAll('.question').length;

  window.addQuestion = function (type = 'MCQ') {
    questionCount++;
    const id = questionCount;
    const wrap = document.createElement('div');
    wrap.className = 'question';
    wrap.dataset.questionId = id;

    wrap.innerHTML = `
      <div class="question-header">
        <h3>Question #${id}</h3>
        <button type="button" class="btn btn-danger btn-sm" onclick="removeQuestion(${id})">
          <i class="fas fa-trash"></i> Remove
        </button>
      </div>

      <div class="form-group">
        <label>Question Text</label>
        <textarea name="questions[${id}][text]" required></textarea>
      </div>

      <div class="form-group">
        <label>Question Type</label>
        <select name="questions[${id}][type]" onchange="changeQuestionType(${id}, this.value)">
          <option value="MCQ" ${type==='MCQ'?'selected':''}>Multiple Choice</option>
          <option value="TrueFalse" ${type==='TrueFalse' || type==='TF'?'selected':''}>True/False</option>
          <option value="FillBlank" ${type==='FillBlank'?'selected':''}>Fill in the Blank</option>
        </select>
      </div>

      <div class="question-options" id="options-${id}">
        ${type==='MCQ' ? mcqOptionsHTML(id)
          : type==='TrueFalse' || type==='TF' ? tfOptionsHTML()
          : fibOptionsHTML()}
      </div>

      <div class="form-group" id="correct-${id}">
        <label>Correct Answer</label>
        ${type==='MCQ' ? mcqCorrectHTML(id)
          : type==='TrueFalse' || type==='TF' ? tfCorrectHTML(id)
          : fibCorrectHTML(id)}
      </div>
    `;
    questionsContainer.appendChild(wrap);
  };

  window.removeQuestion = function (id) {
    const q = document.querySelector(`.question[data-question-id="${id}"]`);
    if (q && confirm('Remove this question?')) {
      q.remove();
      renumberQuestions();
    }
  };

  window.changeQuestionType = function (id, type) {
    const opts = document.getElementById(`options-${id}`);
    const correct = document.getElementById(`correct-${id}`);
    if (!opts || !correct) return;

    if (type === 'MCQ') {
      opts.innerHTML = mcqOptionsHTML(id);
      correct.innerHTML = `<label>Correct Answer</label>${mcqCorrectHTML(id)}`;
      updateCorrectAnswerOptions(id);
    } else if (type === 'TrueFalse' || type === 'TF') {
      opts.innerHTML = tfOptionsHTML();
      correct.innerHTML = `<label>Correct Answer</label>${tfCorrectHTML(id)}`;
    } else {
      opts.innerHTML = fibOptionsHTML();
      correct.innerHTML = `<label>Correct Answer</label>${fibCorrectHTML(id)}`;
    }
  };

  function mcqOptionsHTML(id) {
    return `
      <div class="options-container">
        <label>Options (at least 2)</label>
        <div class="option-inputs">
          <div class="option-input">
            <input type="text" name="questions[${id}][options][]" placeholder="Option 1" required>
            <button type="button" onclick="addOption(${id})">+</button>
          </div>
          <div class="option-input">
            <input type="text" name="questions[${id}][options][]" placeholder="Option 2" required>
            <button type="button" onclick="removeOption(this)">-</button>
          </div>
        </div>
      </div>`;
  }
  function tfOptionsHTML() { return `<p>True/False options are predefined.</p>`; }
  function fibOptionsHTML() { return `<p>Student will type the answer in a text box.</p>`; }

  function mcqCorrectHTML(id) {
    return `<select name="questions[${id}][correct]" required>
      <option value="">Select correct</option>
      <option value="0">Option 1</option>
      <option value="1">Option 2</option>
    </select>`;
  }
  function tfCorrectHTML(id) {
    return `<select name="questions[${id}][correct]" required>
      <option value="">Select correct</option>
      <option value="True">True</option>
      <option value="False">False</option>
    </select>`;
  }
  function fibCorrectHTML(id) {
    return `<input type="text" name="questions[${id}][correct]" placeholder="Correct text" required>`;
  }

  window.addOption = function (id) {
    const list = document.querySelector(`#options-${id} .option-inputs`);
    if (!list) return;
    const idx = list.children.length + 1;
    const row = document.createElement('div');
    row.className = 'option-input';
    row.innerHTML = `
      <input type="text" name="questions[${id}][options][]" placeholder="Option ${idx}" required>
      <button type="button" onclick="removeOption(this)">-</button>`;
    list.appendChild(row);
    updateCorrectAnswerOptions(id);
  };

  window.removeOption = function (btn) {
    const list = btn.closest('.option-inputs');
    if (!list) return;
    if (list.children.length > 2) {
      btn.closest('.option-input').remove();
      const qId = btn.closest('.question')?.dataset?.questionId;
      if (qId) updateCorrectAnswerOptions(qId);
    } else {
      alert('Need at least 2 options');
    }
  };

  function updateCorrectAnswerOptions(id) {
    const sel = document.querySelector(`.question[data-question-id="${id}"] select[name$="[correct]"]`);
    const inputs = document.querySelectorAll(`#options-${id} .option-input input`);
    if (!sel) return;
    sel.innerHTML = '<option value="">Select correct</option>';
    inputs.forEach((_, i) => {
      sel.innerHTML += `<option value="${i}">Option ${i + 1}</option>`;
    });
  }

  function renumberQuestions() {
    document.querySelectorAll('.question').forEach((q, i) => {
      const id = i + 1;
      q.dataset.questionId = id;
      const h = q.querySelector('h3');
      if (h) h.textContent = `Question #${id}`;
    });
    questionCount = document.querySelectorAll('.question').length;
  }
}

/* ============
   Timer
=========== */
function initQuizTimer() {
  window.quizTimer = {
    timer: null,
    timeLeft: 0,
    displayElement: null,
    start: function (seconds, displayEl) {
      this.timeLeft = Number(seconds) || 0;
      this.displayElement = displayEl;
      this.updateDisplay();
      clearInterval(this.timer);
      this.timer = setInterval(() => {
        this.timeLeft--;
        this.updateDisplay();
        if (this.timeLeft <= 0) {
          clearInterval(this.timer);
          if (typeof submitQuiz === 'function') submitQuiz();
        }
      }, 1000);
    },
    updateDisplay: function () {
      if (this.displayElement) {
        this.displayElement.textContent = `Time left: ${this.timeLeft} seconds`;
        if (this.timeLeft < 60) this.displayElement.classList.add('time-warning');
      }
    }
  };
}

/* ==========================
   Submission + Autosave
========================== */
function progressKey() {
  return 'quizProgress:' + (window.QUIZ_ID || 'unknown');
}
function clearQuizProgress() {
  try { localStorage.removeItem(progressKey()); } catch(e) {}
}

function findQuizForm() {
  return document.getElementById('quiz_form') || document.getElementById('quiz-form');
}

function initQuizSubmission() {
  const form = findQuizForm();

  window.submitQuiz = function () {
    const f = findQuizForm();
    if (!f) return;
    clearQuizProgress(); // ✅ clear before submit
    f.submit();
  };

  if (form) {
    form.addEventListener('submit', clearQuizProgress); // ✅ clear on manual submit
  }

  // autosave every 30s
  setInterval(() => { saveQuizProgress(); }, 30000);
}

window.saveQuizProgress = function () {
  const form = findQuizForm();
  if (!form) return;
  const data = {};
  new FormData(form).forEach((v, k) => (data[k] = v));
  try { localStorage.setItem(progressKey(), JSON.stringify(data)); } catch(e) {}
};

window.loadQuizProgress = function () {
  let saved = null;
  try { saved = localStorage.getItem(progressKey()); } catch(e) {}
  if (!saved) return;
  const data = JSON.parse(saved);
  Object.entries(data).forEach(([k, v]) => {
    const els = document.querySelectorAll(`[name="${k}"]`);
    if (!els.length) return;
    els.forEach(el => {
      if (el.type === 'radio' || el.type === 'checkbox') {
        if (el.value === v) el.checked = true;
      } else {
        el.value = v;
      }
    });
  });
};

/* ==========================
   SAFE Student Rendering
========================== */

function s_el(tag, props = {}, children = []) {
  const node = document.createElement(tag);
  Object.entries(props).forEach(([k, v]) => {
    if (k === 'className') node.className = v;
    else if (k === 'text') node.textContent = v;
    else if (k in node) node[k] = v;
    else node.setAttribute(k, v);
  });
  (Array.isArray(children) ? children : [children]).forEach(c => c && node.appendChild(c));
  return node;
}

function normalizeType(t) {
  const x = String(t || '').trim().toLowerCase();
  if (['mcq','multiple choice','multiple_choice'].includes(x)) return 'MCQ';
  if (['truefalse','tf','true_false','true/false'].includes(x)) return 'TrueFalse';
  if (['fillblank','fib','fill','fill_in_the_blank','fill in the blank'].includes(x)) return 'FillBlank';
  return 'FillBlank';
}

// If options is an array → use numeric index values ("0","1",...)
// If options is an object → use the object's keys as values.
function toUniformOptions(opts) {
  if (!opts) return [];
  if (Array.isArray(opts)) {
    return opts.map((text, idx) => ({ value: String(idx), label: String(text) }));
  }
  if (typeof opts === 'object') {
    return Object.keys(opts).map(k => ({ value: String(k), label: String(opts[k]) }));
  }
  return [];
}

window.renderQuiz = function (questions, timeLimitSeconds) {
  questions = Array.isArray(questions) ? questions.slice().sort(() => Math.random() - 0.5) : [];

  const holder = document.getElementById('questions_container');
  const nav = document.getElementById('question-nav');
  if (!holder) return;
  holder.innerHTML = '';
  if (nav) nav.innerHTML = '';

  questions.forEach((q, i) => {
    const type = normalizeType(q.type);
    const card = s_el('div', { className: 'quiz-question' });

    const title = s_el('p', { text: `${i + 1}. ${q.text || ''}` });
    card.appendChild(title);

    if (type === 'MCQ') {
      const wrap = s_el('div', { className: 'options' });
      const items = toUniformOptions(q.options);
      if (!items.length) wrap.appendChild(s_el('p', { text: 'No options provided.' }));

      items.forEach((opt, idx) => {
        const lab = s_el('label');
        const input = s_el('input', { type: 'radio', name: `answers[${q.question_id}]`, value: opt.value });
        const prefix = s_el('span', { className: 'opt-key', text: `${String.fromCharCode(65 + idx)}. ` });
        const txt = s_el('span', { className: 'opt-text', text: opt.label });
        lab.appendChild(input); lab.appendChild(prefix); lab.appendChild(txt);
        wrap.appendChild(lab);
      });
      card.appendChild(wrap);

    } else if (type === 'TrueFalse') {
      const wrap = s_el('div', { className: 'options' });
      ['True','False'].forEach(v => {
        const lab = s_el('label');
        const input = s_el('input', { type: 'radio', name: `answers[${q.question_id}]`, value: v });
        lab.appendChild(input); lab.appendChild(s_el('span', { text: v }));
        wrap.appendChild(lab);
      });
      card.appendChild(wrap);

    } else { // FillBlank
      const wrap = s_el('div', { className: 'options' });
      wrap.appendChild(s_el('input', {
        type: 'text',
        name: `answers[${q.question_id}]`,
        placeholder: 'Type your answer here',
        autocomplete: 'off'
      }));
      card.appendChild(wrap);
    }

    holder.appendChild(card);

    if (nav) {
      const pill = s_el('button', { type: 'button', className: 'nav-item', text: String(i + 1) });
      pill.addEventListener('click', () => {
        document.querySelectorAll('.quiz-question').forEach((el, j) => el.classList.toggle('active', j === i));
        nav.querySelectorAll('.nav-item').forEach((b, j) => b.classList.toggle('active', j === i));
      });
      nav.appendChild(pill);
    }
  });

  if (nav) {
    document.querySelectorAll('.quiz-question').forEach((el, j) => el.classList.toggle('active', j === 0));
    const first = nav.querySelector('.nav-item'); if (first) first.classList.add('active');
  } else {
    document.querySelectorAll('.quiz-question').forEach(el => el.classList.add('active'));
  }

  const timerEl = document.getElementById('timer');
  if (timerEl && window.quizTimer && typeof window.quizTimer.start === 'function') {
    window.quizTimer.start(timeLimitSeconds, timerEl);
  }

  loadQuizProgress();
};
