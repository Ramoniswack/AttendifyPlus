// My Attendance JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Add hover effects for cards
    const cards = document.querySelectorAll('.card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Add click effects for buttons
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('click', function() {
            // Add ripple effect
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = event.clientX - rect.left - size / 2;
            const y = event.clientY - rect.top - size / 2;
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.classList.add('ripple');
            
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
    
    // Auto-submit form on filter change
    const filterForm = document.querySelector('form');
    const filterInputs = filterForm.querySelectorAll('select, input');
    
    filterInputs.forEach(input => {
        input.addEventListener('change', function() {
            // Add loading state
            const submitBtn = document.createElement('button');
            submitBtn.type = 'submit';
            submitBtn.style.display = 'none';
            filterForm.appendChild(submitBtn);
            submitBtn.click();
        });
    });
});

// Export attendance report
function exportReport() {
    const subject = document.querySelector('select[name="subject"]').value;
    const month = document.querySelector('input[name="month"]').value;
    
    let url = '../../api/export_student_attendance.php?';
    if (subject) url += `subject=${subject}&`;
    if (month) url += `month=${month}`;
    
    window.open(url, '_blank');
}

// Add CSS for ripple effect
const style = document.createElement('style');
style.textContent = `
    .btn {
        position: relative;
        overflow: hidden;
    }
    
    .ripple {
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.6);
        transform: scale(0);
        animation: ripple-animation 0.6s linear;
        pointer-events: none;
    }
    
    @keyframes ripple-animation {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
    
    /* Dark mode ripple */
    body.dark-mode .ripple {
        background: rgba(0, 255, 200, 0.3);
    }
    
    /* Loading state for form submission */
    .form-loading {
        opacity: 0.7;
        pointer-events: none;
    }
    
    .form-loading::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 20px;
        height: 20px;
        margin: -10px 0 0 -10px;
        border: 2px solid #f3f3f3;
        border-top: 2px solid var(--accent-light);
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    body.dark-mode .form-loading::after {
        border-top-color: var(--accent-dark);
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);

// Performance tracking
function trackAttendanceMetrics() {
    const attendanceRate = document.querySelector('.mini-stat-value');
    const presentCount = document.querySelectorAll('.mini-stat-value')[1];
    const absentCount = document.querySelectorAll('.mini-stat-value')[2];
    const lateCount = document.querySelectorAll('.mini-stat-value')[3];
    
    const metrics = {
        attendanceRate: attendanceRate ? attendanceRate.textContent : '0%',
        presentCount: presentCount ? presentCount.textContent : '0',
        absentCount: absentCount ? absentCount.textContent : '0',
        lateCount: lateCount ? lateCount.textContent : '0'
    };
    
    console.log('Attendance Metrics:', metrics);
    return metrics;
}

// Initialize performance tracking
document.addEventListener('DOMContentLoaded', function() {
    // Track initial metrics
    setTimeout(trackAttendanceMetrics, 1000);
});

// Responsive adjustments
function handleResponsiveLayout() {
    const cards = document.querySelectorAll('.card');
    const table = document.querySelector('.table');
    
    if (window.innerWidth < 768) {
        cards.forEach(card => {
            card.style.marginBottom = '1rem';
        });
        
        if (table) {
            table.style.fontSize = '0.85rem';
        }
    } else {
        cards.forEach(card => {
            card.style.marginBottom = '0';
        });
        
        if (table) {
            table.style.fontSize = '1rem';
        }
    }
}

// Listen for window resize
window.addEventListener('resize', handleResponsiveLayout);

// Initialize responsive layout
document.addEventListener('DOMContentLoaded', handleResponsiveLayout);