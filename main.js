// main.js - Core functionality for the quiz platform

// DOM ready function
document.addEventListener('DOMContentLoaded', function() {
    initializePlatform();
    // Real-time fetch for Top Performing Quiz and Recent Activity
    setInterval(fetchTopPerformingQuiz, 10000);  // Update top-performing quiz every 10 seconds
    setInterval(fetchRecentActivity, 10000);    // Update recent activity every 10 seconds
});

// Platform initialization
function initializePlatform() {
    // Initialize tooltips
    initTooltips();
    
    // Initialize notifications
    initNotifications();
    
    // Initialize modals
    initModals();
    
    // Initialize form validation
    initFormValidation();
    
    // Initialize copy functionality
    initCopyButtons();
}

// Tooltip functionality
function initTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
    
    function showTooltip(e) {
        const tooltipText = this.getAttribute('data-tooltip');
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = tooltipText;
        document.body.appendChild(tooltip);
        
        const rect = this.getBoundingClientRect();
        tooltip.style.top = (rect.top - tooltip.offsetHeight - 10) + 'px';
        tooltip.style.left = (rect.left + (rect.width - tooltip.offsetWidth) / 2) + 'px';
        
        this.setAttribute('data-tooltip-id', tooltip.textContent);
    }
    
    function hideTooltip() {
        const tooltips = document.querySelectorAll('.tooltip');
        tooltips.forEach(tooltip => tooltip.remove());
    }
}

// Notification system
function initNotifications() {
    window.showNotification = function(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="notification-icon ${getNotificationIcon(type)}"></i>
                <span>${message}</span>
            </div>
            <button class="notification-close">&times;</button>
        `;
        
        document.body.appendChild(notification);
        
        // Add close event
        notification.querySelector('.notification-close').addEventListener('click', function() {
            notification.remove();
        });
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    };
    
    function getNotificationIcon(type) {
        const icons = {
            'success': 'fas fa-check-circle',
            'error': 'fas fa-exclamation-circle',
            'warning': 'fas fa-exclamation-triangle',
            'info': 'fas fa-info-circle'
        };
        return icons[type] || icons.info;
    }
}

// Modal functionality
function initModals() {
    const modalTriggers = document.querySelectorAll('[data-modal]');
    const modals = document.querySelectorAll('.modal');
    
    // Open modal
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function() {
            const modalId = this.getAttribute('data-modal');
            const modal = document.getElementById(modalId);
            if (modal) {
                openModal(modal);
            }
        });
    });
    
    // Close modals
    modals.forEach(modal => {
        const closeBtn = modal.querySelector('.modal-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => closeModal(modal));
        }
        
        // Close when clicking outside
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(modal);
            }
        });
    });
    
    window.openModal = function(modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    };
    
    window.closeModal = function(modal) {
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
    };
}

// Form validation
function initFormValidation() {
    const forms = document.querySelectorAll('form[data-validate]');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });
    });
    
    function validateForm(form) {
        let isValid = true;
        const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        
        inputs.forEach(input => {
            if (!input.value.trim()) {
                markInvalid(input, 'This field is required');
                isValid = false;
            } else {
                markValid(input);
                
                // Email validation
                if (input.type === 'email' && !isValidEmail(input.value)) {
                    markInvalid(input, 'Please enter a valid email address');
                    isValid = false;
                }
                
                // Password strength
                if (input.type === 'password' && input.value.length < 6) {
                    markInvalid(input, 'Password must be at least 6 characters');
                    isValid = false;
                }
            }
        });
        
        return isValid;
    }
    
    function markInvalid(input, message) {
        input.classList.add('invalid');
        input.classList.remove('valid');
        
        // Remove existing error message
        const existingError = input.parentNode.querySelector('.error-message');
        if (existingError) {
            existingError.remove();
        }
        
        // Add error message
        const error = document.createElement('div');
        error.className = 'error-message';
        error.textContent = message;
        input.parentNode.appendChild(error);
    }
    
    function markValid(input) {
        input.classList.remove('invalid');
        input.classList.add('valid');
        
        // Remove error message
        const error = input.parentNode.querySelector('.error-message');
        if (error) {
            error.remove();
        }
    }
    
    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
}

// Copy to clipboard functionality
function initCopyButtons() {
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('copy-btn') || e.target.closest('.copy-btn')) {
            const button = e.target.classList.contains('copy-btn') ? e.target : e.target.closest('.copy-btn');
            const textToCopy = button.getAttribute('data-copy') || button.textContent;
            
            navigator.clipboard.writeText(textToCopy).then(() => {
                showNotification('Copied to clipboard!', 'success');
                
                // Visual feedback
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check"></i> Copied!';
                
                setTimeout(() => {
                    button.innerHTML = originalText;
                }, 2000);
            }).catch(err => {
                showNotification('Failed to copy text', 'error');
                console.error('Failed to copy: ', err);
            });
        }
    });
}

// AJAX helper functions
window.makeRequest = function(url, options = {}) {
    const { method = 'GET', data = null, headers = {} } = options;
    
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open(method, url);
        
        // Set headers
        xhr.setRequestHeader('Content-Type', 'application/json');
        for (const [key, value] of Object.entries(headers)) {
            xhr.setRequestHeader(key, value);
        }
        
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    resolve(JSON.parse(xhr.responseText));
                } catch (e) {
                    resolve(xhr.responseText);
                }
            } else {
                reject({
                    status: xhr.status,
                    statusText: xhr.statusText
                });
            }
        };
        
        xhr.onerror = function() {
            reject({
                status: xhr.status,
                statusText: xhr.statusText
            });
        };
        
        xhr.send(data ? JSON.stringify(data) : null);
    });
};

// Debounce function for search inputs
window.debounce = function(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
};

// Format time function
window.formatTime = function(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
};

// Real-time fetch for Top Performing Quiz
async function fetchTopPerformingQuiz() {
    try {
        const response = await fetch('/api/top-performing-quiz');
        if (!response.ok) {
            throw new Error('Failed to fetch top performing quiz');
        }
        const data = await response.json();
        document.getElementById('top-performing-quiz').innerText = data.quizName;
    } catch (error) {
        console.error('Error fetching top performing quiz:', error);
        document.getElementById('top-performing-quiz').innerText = 'Error loading data';
    }
}

// Real-time fetch for Recent Activity
async function fetchRecentActivity() {
    try {
        const response = await fetch('/api/recent-activity');
        if (!response.ok) {
            throw new Error('Failed to fetch recent activity');
        }
        const activities = await response.json();
        const activityList = document.getElementById('recent-activity-list');
        activityList.innerHTML = '';  // Clear current activities
        activities.forEach(activity => {
            const listItem = document.createElement('li');
            listItem.innerText = activity.description;
            activityList.appendChild(listItem);
        });
    } catch (error) {
        console.error('Error fetching recent activity:', error);
        const activityList = document.getElementById('recent-activity-list');
        activityList.innerHTML = '<li>Error loading activity</li>';
    }
}
