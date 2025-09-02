// script.js - Teacher quiz creation (manual + pre-made sets)
// All DOM built with createElement (no innerHTML for user data).

// ---------------------------
// Utilities
// ---------------------------
function qCount(container) {
  return container.querySelectorAll('.question-block').length;
}

function make(tag, props = {}, children = []) {
  const el = document.createElement(tag);
  Object.entries(props).forEach(([k, v]) => {
    if (k === 'className') el.className = v;
    else if (k === 'text') el.textContent = v;
    else if (k === 'html') el.innerHTML = v; // not used for user data
    else if (k === 'on') {
      Object.entries(v).forEach(([evt, fn]) => el.addEventListener(evt, fn));
    } else if (k in el) {
      el[k] = v;
    } else {
      el.setAttribute(k, v);
    }
  });
  (Array.isArray(children) ? children : [children]).forEach(c => c && el.appendChild(c));
  return el;
}

function makeInput(name, value = '', placeholder = '', required = true) {
  return make('input', {
    type: 'text',
    name,
    value,
    placeholder,
    required
  });
}

function makeSelect(name, options, selectedValue) {
  const sel = make('select', { name });
  options.forEach(opt => {
    const o = make('option', { value: opt.value, text: opt.label });
    if (selectedValue != null && String(selectedValue) === String(opt.value)) {
      o.selected = true;
    }
    sel.appendChild(o);
  });
  return sel;
}

// ---------------------------
// Add Question (manual)
// ---------------------------
function addQuestion() {
  const container = document.getElementById('questions');
  if (!container) {
    alert('Questions container not found');
    return;
  }

  const index = qCount(container);

  const textInput = makeInput(`questions[${index}][text]`, '', 'Question Text', true);

  const select = make('select', {
    name: `questions[${index}][type]`,
  }, [
    make('option', { value: 'MCQ', text: 'MCQ' }),
    make('option', { value: 'TrueFalse', text: 'True/False' }),
    make('option', { value: 'FillBlank', text: 'Fill in the Blank' })
  ]);

  const optsDiv = make('div', { id: `options_${index}` });

  // Correct field container (content changes by type)
  const correctWrap = make('div', { id: `correct_${index}` });

  const removeBtn = make('button', {
    type: 'button',
    text: 'Remove',
    on: { click: () => removeQuestion(index) }
  });

  const block = make('div', { id: `question-${index}`, className: 'question-block' }, [
    textInput,
    select,
    optsDiv,
    correctWrap,
    removeBtn
  ]);

  container.appendChild(block);

  // Default MCQ fields
  renderTypeFields(select, index);
  select.addEventListener('change', () => renderTypeFields(select, index));
}

// ---------------------------
// Remove Question
// ---------------------------
function removeQuestion(id) {
  const el = document.getElementById('question-' + id);
  if (el && confirm('Are you sure you want to remove this question?')) {
    el.remove();
  }
}

// ---------------------------
// Render fields by type (replaces old toggleOptions)
// ---------------------------
function renderTypeFields(select, index, preset = {}) {
  // Options container
  const optionsDiv = document.getElementById(`options_${index}`);
  // Correct container
  const correctDiv = document.getElementById(`correct_${index}`);
  if (!optionsDiv || !correctDiv) return;

  optionsDiv.replaceChildren();
  correctDiv.replaceChildren();

  const type = select.value;

  if (type === 'MCQ') {
    // Four option inputs, keys a/b/c/d
    const letters = ['a', 'b', 'c', 'd'];
    letters.forEach(letter => {
      optionsDiv.appendChild(
        makeInput(`questions[${index}][options][${letter}]`, preset.options?.[letter] || '', `Option ${letter.toUpperCase()}`, true)
      );
    });

    // Correct dropdown (a/b/c/d)
    const corrSel = makeSelect(
      `questions[${index}][correct]`,
      letters.map(l => ({ value: l, label: l.toUpperCase() })),
      preset.correct != null ? String(preset.correct).toLowerCase() : 'a'
    );
    const corrLbl = make('label', { text: 'Correct: ' });
    corrLbl.appendChild(corrSel);
    correctDiv.appendChild(corrLbl);

  } else if (type === 'TrueFalse') {
    // Informative text + correct dropdown True/False
    optionsDiv.appendChild(make('p', { text: 'Options: True / False (fixed)' }));

    const tfOptions = [
      { value: 'True', label: 'True' },
      { value: 'False', label: 'False' }
    ];
    const corrSel = makeSelect(`questions[${index}][correct]`, tfOptions, preset.correct || 'True');
    const corrLbl = make('label', { text: 'Correct: ' });
    corrLbl.appendChild(corrSel);
    correctDiv.appendChild(corrLbl);

  } else if (type === 'FillBlank') {
    optionsDiv.appendChild(make('p', { text: 'Answer will be typed by student.' }));
    const correctInput = makeInput(
      `questions[${index}][correct]`,
      preset.correct || '',
      'Correct Answer (exact text / acceptable value)',
      true
    );
    correctDiv.appendChild(correctInput);
  }
}

// Backward compatible alias (if HTML calls toggleOptions)
function toggleOptions(select, index) {
  renderTypeFields(select, index);
}

/* ==========================================================
   SAMPLE QUESTION SETS FOR TEACHER (WEB PROGRAMMING)
   (All inserted safely via DOM; <tags> are fine to include.)
========================================================== */

// Helper to insert a pre-filled question
function insertQuestion(q) {
  const container = document.getElementById('questions');
  const index = qCount(container);

  const textInput = makeInput(`questions[${index}][text]`, q.text, 'Question Text', true);

  const select = make('select', {
    name: `questions[${index}][type]`
  }, [
    make('option', { value: 'MCQ', text: 'MCQ', selected: q.type === 'MCQ' }),
    make('option', { value: 'TrueFalse', text: 'True/False', selected: q.type === 'TrueFalse' }),
    make('option', { value: 'FillBlank', text: 'Fill in the Blank', selected: q.type === 'FillBlank' })
  ]);

  const optsDiv = make('div', { id: `options_${index}` });
  const correctWrap = make('div', { id: `correct_${index}` });

  const removeBtn = make('button', {
    type: 'button',
    text: 'Remove',
    on: { click: () => removeQuestion(index) }
  });

  const block = make('div', { id: `question-${index}`, className: 'question-block' }, [
    textInput,
    select,
    optsDiv,
    correctWrap,
    removeBtn
  ]);

  container.appendChild(block);

  // Render with preset values (so correct dropdown/text is preselected)
  renderTypeFields(select, index, {
    options: q.options || null,
    correct: q.correct
  });

  // Keep responsive to later changes
  select.addEventListener('change', () => renderTypeFields(select, index));
}

// ---------------------------
// Pre-made Sets
// ---------------------------

// 1) MCQ Set
function addMCQSet() {
  const set = [
    {
      text: "Which HTML element is used to create a hyperlink?",
      type: "MCQ",
      options: { a: "Anchor element", b: "Link (stylesheet) element", c: "URL element", d: "Button element" },
      correct: "a"
    },
    {
      text: "Which of the following is NOT a CSS position value?",
      type: "MCQ",
      options: { a: "absolute", b: "relative", c: "fixed", d: "floating" },
      correct: "d"
    },
    {
      text: "Which JavaScript method selects an element by its id?",
      type: "MCQ",
      options: { a: "document.getElementById", b: "document.querySelectorAll", c: "document.getElementsByClassName", d: "document.getNode" },
      correct: "a"
    },
    {
      text: "In HTML5, which element is used to play video files?",
      type: "MCQ",
      options: { a: "media element", b: "movie element", c: "video element", d: "play element" },
      correct: "c"
    },
    {
      text: "Which of these is a server-side language?",
      type: "MCQ",
      options: { a: "JavaScript", b: "PHP", c: "CSS", d: "HTML" },
      correct: "b"
    }
  ];
  set.forEach(insertQuestion);
}

// 2) True/False Set
function addTFSet() {
  const set = [
    { text: "CSS stands for Cascading Style Sheets.", type: "TrueFalse", correct: "True" },
    { text: "HTML is used for structuring web pages.", type: "TrueFalse", correct: "True" },
    { text: "JavaScript can only be executed on the server side.", type: "TrueFalse", correct: "False" },
    { text: "The <form> element is used to create interactive forms in HTML.", type: "TrueFalse", correct: "True" },
    { text: "In CSS, the '#' symbol is used for selecting classes.", type: "TrueFalse", correct: "False" }
  ];
  set.forEach(insertQuestion);
}

// 3) Fill in the Blanks Set
function addFIBSet() {
  const set = [
    { text: "The ______ tag in HTML is used to insert an image.", type: "FillBlank", correct: "<img>" },
    { text: "JavaScript function to print in console is ______.", type: "FillBlank", correct: "console.log" },
    { text: "In CSS, the property to change text color is ______.", type: "FillBlank", correct: "color" },
    { text: "The HTML5 tag used for drawing graphics is ______.", type: "FillBlank", correct: "<canvas>" },
    { text: "To link an external CSS file, we use the ______ tag.", type: "FillBlank", correct: "<link>" }
  ];
  set.forEach(insertQuestion);
}

// 4) Mixed Set
function addMixedSet() {
  const set = [
    {
      text: "Which of the following is a CSS framework?",
      type: "MCQ",
      options: { a: "React", b: "Bootstrap", c: "Vue", d: "Angular" },
      correct: "b"
    },
    { text: "HTML tables are created using <table>, <tr>, and <td> tags.", type: "TrueFalse", correct: "True" },
    { text: "The ______ attribute in <a> defines the link destination.", type: "FillBlank", correct: "href" },
    {
      text: "Which HTML tag is used to define a navigation bar?",
      type: "MCQ",
      options: { a: "<nav>", b: "<header>", c: "<menu>", d: "<navigation>" },
      correct: "a"
    },
    { text: "In JavaScript, '==' checks both value and type.", type: "TrueFalse", correct: "False" }
  ];
  set.forEach(insertQuestion);
}

// Expose globally
window.addQuestion = addQuestion;
window.removeQuestion = removeQuestion;
window.toggleOptions = toggleOptions; // alias
window.addMCQSet = addMCQSet;
window.addTFSet = addTFSet;
window.addFIBSet = addFIBSet;
window.addMixedSet = addMixedSet;
window.insertQuestion = insertQuestion;
