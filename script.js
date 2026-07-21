// ============================================
// CUSTOM JAVASCRIPT
// ============================================

$(document).ready(function() {
    
    // ============================
    // Auto-hide alerts after 5 seconds
    // ============================
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);
    
    // ============================
    // Confirm delete
    // ============================
    $('.delete-confirm').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this record?')) {
            e.preventDefault();
        }
    });
    
    // ============================
    // Toggle password visibility
    // ============================
    $('.toggle-password').on('click', function() {
        var input = $($(this).data('target'));
        var icon = $(this).find('i');
        
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            input.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });
    
    // ============================
    // Form validation
    // ============================
    $('.needs-validation').on('submit', function(e) {
        if (this.checkValidity() === false) {
            e.preventDefault();
            e.stopPropagation();
        }
        $(this).addClass('was-validated');
    });
    
    // ============================
    // Search functionality
    // ============================
    $('.search-input').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        var target = $(this).data('target');
        
        $(target + ' tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });
    
    // ============================
    // Print functionality
    // ============================
    $('.print-btn').on('click', function() {
        window.print();
    });
    
    // ============================
    // Export to CSV
    // ============================
    $('.export-csv').on('click', function() {
        var table = $(this).data('target');
        var csv = [];
        var rows = $(table + ' tr');
        
        rows.each(function() {
            var row = [];
            $(this).find('th, td').each(function() {
                row.push('"' + $(this).text().replace(/"/g, '""') + '"');
            });
            csv.push(row.join(','));
        });
        
        var csvContent = csv.join('\n');
        var blob = new Blob([csvContent], { type: 'text/csv' });
        var url = URL.createObjectURL(blob);
        
        var a = document.createElement('a');
        a.href = url;
        a.download = 'export.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });
    
    // ============================
    // Auto-update dashboard stats
    // ============================
    if ($('.stats-card').length > 0) {
        setInterval(function() {
            $.ajax({
                url: 'dashboard_ajax.php',
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    // Update stats dynamically
                },
                error: function() {
                    // Handle error
                }
            });
        }, 60000); // Update every 60 seconds
    }
    
    // ============================
    // Tooltips
    // ============================
    $('[data-toggle="tooltip"]').tooltip();
    
    // ============================
    // Popovers
    // ============================
    $('[data-toggle="popover"]').popover();
    
});

// ============================
// Custom functions
// ============================

/**
 * Format currency
 */
function formatCurrency(amount) {
    return 'TSh ' + parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

/**
 * Format date
 */
function formatDate(dateString) {
    var date = new Date(dateString);
    var options = { year: 'numeric', month: 'short', day: 'numeric' };
    return date.toLocaleDateString('en-US', options);
}

/**
 * Get time ago
 */
function timeAgo(dateString) {
    var now = new Date();
    var past = new Date(dateString);
    var diff = Math.floor((now - past) / 1000);
    
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
    if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
    if (diff < 604800) return Math.floor(diff / 86400) + ' days ago';
    return formatDate(dateString);
}