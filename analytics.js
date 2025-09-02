// analytics.js - Analytics and chart functionality

document.addEventListener('DOMContentLoaded', function() {
    initAnalytics();
});

function initAnalytics() {
    // Load analytics data
    loadAnalyticsData();
    
    // Initialize charts
    initCharts();
    
    // Initialize filters
    initAnalyticsFilters();
}

// Load analytics data
function loadAnalyticsData() {
    // This would typically fetch data from the server
    const quizId = getQueryParam('quiz_id');
    
    if (quizId) {
        // Load specific quiz analytics
        fetchQuizAnalytics(quizId);
    } else {
        // Load overall analytics
        fetchOverallAnalytics();
    }
}

// Fetch quiz analytics
function fetchQuizAnalytics(quizId) {
    makeRequest(`/api/analytics/quiz/${quizId}`)
        .then(data => {
            renderQuizAnalytics(data);
        })
        .catch(error => {
            console.error('Error fetching quiz analytics:', error);
            showNotification('Failed to load analytics data', 'error');
        });
}

// Fetch overall analytics
function fetchOverallAnalytics() {
    makeRequest('/api/analytics/overall')
        .then(data => {
            renderOverallAnalytics(data);
        })
        .catch(error => {
            console.error('Error fetching overall analytics:', error);
            showNotification('Failed to load analytics data', 'error');
        });
}

// Render quiz analytics
function renderQuizAnalytics(data) {
    // Render summary stats
    renderSummaryStats(data.summary);
    
    // Render charts
    renderScoreDistributionChart(data.scoreDistribution);
    renderTimeSpentChart(data.timeSpent);
    renderQuestionAnalysisChart(data.questionAnalysis);
    
    // Render participant list
    renderParticipantList(data.participants);
}

// Render overall analytics
function renderOverallAnalytics(data) {
    // Render summary stats
    renderSummaryStats(data.summary);
    
    // Render charts
    renderQuizPerformanceChart(data.quizPerformance);
    renderCategoryPerformanceChart(data.categoryPerformance);
    renderActivityOverTimeChart(data.activityOverTime);
}

// Render summary statistics
function renderSummaryStats(stats) {
    const statsContainer = document.getElementById('summary-stats');
    if (!statsContainer) return;
    
    statsContainer.innerHTML = `
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-value">${stats.averageScore}%</div>
            <div class="stat-label">Average Score</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-value">${stats.totalParticipants}</div>
            <div class="stat-label">Total Participants</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-value">${stats.averageTime}</div>
            <div class="stat-label">Avg. Time</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-trophy"></i>
            </div>
            <div class="stat-value">${stats.highestScore}%</div>
            <div class="stat-label">Highest Score</div>
        </div>
    `;
}

// Initialize charts
function initCharts() {
    // This would use a charting library like Chart.js
    // For now, we'll just set up placeholder functions
}

// Render score distribution chart
function renderScoreDistributionChart(data) {
    const ctx = document.getElementById('score-distribution-chart');
    if (!ctx) return;
    
    // This would use Chart.js to render the chart
    console.log('Rendering score distribution chart with data:', data);
}

// Render time spent chart
function renderTimeSpentChart(data) {
    const ctx = document.getElementById('time-spent-chart');
    if (!ctx) return;
    
    // This would use Chart.js to render the chart
    console.log('Rendering time spent chart with data:', data);
}

// Render question analysis chart
function renderQuestionAnalysisChart(data) {
    const ctx = document.getElementById('question-analysis-chart');
    if (!ctx) return;
    
    // This would use Chart.js to render the chart
    console.log('Rendering question analysis chart with data:', data);
}

// Render quiz performance chart
function renderQuizPerformanceChart(data) {
    const ctx = document.getElementById('quiz-performance-chart');
    if (!ctx) return;
    
    // This would use Chart.js to render the chart
    console.log('Rendering quiz performance chart with data:', data);
}

// Render category performance chart
function renderCategoryPerformanceChart(data) {
    const ctx = document.getElementById('category-performance-chart');
    if (!ctx) return;
    
    // This would use Chart.js to render the chart
    console.log('Rendering category performance chart with data:', data);
}

// Render activity over time chart
function renderActivityOverTimeChart(data) {
    const ctx = document.getElementById('activity-over-time-chart');
    if (!ctx) return;
    
    // This would use Chart.js to render the chart
    console.log('Rendering activity over time chart with data:', data);
}

// Render participant list
function renderParticipantList(participants) {
    const container = document.getElementById('participant-list');
    if (!container) return;
    
    let html = `
        <table class="participant-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Score</th>
                    <th>Time</th>
                    <th>Completed</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    participants.forEach(participant => {
        html += `
            <tr>
                <td>${participant.name}</td>
                <td>${participant.score}%</td>
                <td>${participant.time}</td>
                <td>${participant.completed}</td>
                <td>
                    <button class="btn btn-sm" onclick="viewParticipantDetails(${participant.id})">
                        <i class="fas fa-eye"></i> Details
                    </button>
                </td>
            </tr>
        `;
    });
    
    html += `
            </tbody>
        </table>
    `;
    
    container.innerHTML = html;
}

// Initialize analytics filters
function initAnalyticsFilters() {
    const dateRangeFilter = document.getElementById('date-range-filter');
    const quizFilter = document.getElementById('quiz-filter');
    const applyFiltersBtn = document.getElementById('apply-filters');
    
    if (applyFiltersBtn) {
        applyFiltersBtn.addEventListener('click', function() {
            const filters = {
                dateRange: dateRangeFilter ? dateRangeFilter.value : '',
                quiz: quizFilter ? quizFilter.value : ''
            };
            
            applyFilters(filters);
        });
    }
}

// Apply filters
function applyFilters(filters) {
    // Show loading state
    showLoadingState();
    
    // Build query string
    const queryParams = new URLSearchParams();
    if (filters.dateRange) queryParams.append('date_range', filters.dateRange);
    if (filters.quiz) queryParams.append('quiz_id', filters.quiz);
    
    // Reload data with filters
    makeRequest(`/api/analytics?${queryParams.toString()}`)
        .then(data => {
            if (getQueryParam('quiz_id')) {
                renderQuizAnalytics(data);
            } else {
                renderOverallAnalytics(data);
            }
        })
        .catch(error => {
            console.error('Error applying filters:', error);
            showNotification('Failed to apply filters', 'error');
        })
        .finally(() => {
            hideLoadingState();
        });
}

// View participant details
window.viewParticipantDetails = function(participantId) {
    makeRequest(`/api/analytics/participant/${participantId}`)
        .then(data => {
            showParticipantDetailsModal(data);
        })
        .catch(error => {
            console.error('Error fetching participant details:', error);
            showNotification('Failed to load participant details', 'error');
        });
};

// Show participant details modal
function showParticipantDetailsModal(data) {
    const modal = document.getElementById('participant-details-modal');
    if (!modal) return;
    
    // Populate modal with data
    const content = modal.querySelector('.modal-content');
    content.innerHTML = `
        <h2>Participant Details: ${data.name}</h2>
        <div class="participant-details">
            <div class="detail-item">
                <label>Email:</label>
                <span>${data.email}</span>
            </div>
            <div class="detail-item">
                <label>Score:</label>
                <span>${data.score}%</span>
            </div>
            <div class="detail-item">
                <label>Time Spent:</label>
                <span>${data.timeSpent}</span>
            </div>
            <div class="detail-item">
                <label>Completed:</label>
                <span>${data.completed}</span>
            </div>
        </div>
        
        <h3>Question Responses</h3>
        <div class="question-responses">
            ${data.responses.map((response, index) => `
                <div class="response-item ${response.correct ? 'correct' : 'incorrect'}">
                    <div class="question-number">Q${index + 1}</div>
                    <div class="question-text">${response.question}</div>
                    <div class="response-answer">
                        <strong>Answer:</strong> ${response.answer}
                        ${response.correct ? 
                            '<span class="correct-indicator"><i class="fas fa-check"></i> Correct</span>' : 
                            `<span class="incorrect-indicator"><i class="fas fa-times"></i> Incorrect</span>
                             <div class="correct-answer">Correct answer: ${response.correctAnswer}</div>`
                        }
                    </div>
                </div>
            `).join('')}
        </div>
    `;
    
    // Show modal
    openModal(modal);
}

// Show loading state
function showLoadingState() {
    const analyticsContent = document.querySelector('.analytics-content');
    if (analyticsContent) {
        analyticsContent.classList.add('loading');
    }
}

// Hide loading state
function hideLoadingState() {
    const analyticsContent = document.querySelector('.analytics-content');
    if (analyticsContent) {
        analyticsContent.classList.remove('loading');
    }
}

// Get query parameter
function getQueryParam(name) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(name);
}

// Export analytics data
window.exportAnalyticsData = function(format = 'csv') {
    const quizId = getQueryParam('quiz_id');
    const exportUrl = quizId ? 
        `/api/analytics/quiz/${quizId}/export?format=${format}` : 
        `/api/analytics/export?format=${format}`;
    
    window.location.href = exportUrl;
};