// Initialize when document is ready
$(document).ready(function() {
    // Handle checkbox selection (only one can be selected)
    $('.request-checkbox').on('change', function() {
        const currentCheckbox = $(this);
        const currentRow = currentCheckbox.closest('tr');
        
        // Remove selection from all checkboxes
        $('.request-checkbox').not(this).prop('checked', false);
        $('tr').removeClass('row-selected');
        
        if (currentCheckbox.is(':checked')) {
            // Highlight the current row if checkbox is checked
            currentRow.addClass('row-selected');
        } else {
            // Reset styles for the current checkbox
            currentCheckbox.css({
                'background-color': 'transparent',
                'border-color': 'rgba(0, 0, 0, 0.25)'
            });
        }
    });
    
    // Initialize selected checkboxes on page load
    $('.request-checkbox:checked').each(function() {
        $(this).closest('tr').addClass('row-selected');
    });
    
    // Initialize datepicker
    $('#datepicker').datepicker({
        format: 'dd.mm.yyyy',
        language: 'ru',
        autoclose: true,
        todayHighlight: true,
        container: '#datepicker'
    });

    // Sync datepicker with input field
    $('#datepicker').on('changeDate', function(e) {
        $('#dateInput').val(e.format('dd.mm.yyyy'));
        $('#selectedDate').text(e.format('dd.mm.yyyy'));
    });

    // Initialize input field datepicker
    $('#dateInput').datepicker({
        format: 'dd.mm.yyyy',
        language: 'ru',
        autoclose: true,
        todayHighlight: true,
        container: '#datepicker'
    });

    // Set today's date on load
    let today = new Date();
    let formattedDate = today.toLocaleDateString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
    $('#datepicker').datepicker('update', today);
    $('#dateInput').val(formattedDate);
    $('#selectedDate').text(formattedDate);

    // Theme toggle functionality
    const themeToggle = document.getElementById('themeToggle');
    const sunIcon = document.getElementById('sunIcon');
    const moonIcon = document.getElementById('moonIcon');
    const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');
    const currentTheme = localStorage.getItem('theme');

    // Check for saved theme preference or use system preference
    if (currentTheme === 'dark' || (!currentTheme && prefersDarkScheme.matches)) {
        document.documentElement.setAttribute('data-bs-theme', 'dark');
        sunIcon.classList.remove('d-none'); // Show sun in dark mode
        moonIcon.classList.add('d-none');
    } else {
        document.documentElement.setAttribute('data-bs-theme', 'light');
        sunIcon.classList.add('d-none');
        moonIcon.classList.remove('d-none'); // Show moon in light mode
    }

    // Toggle theme on icon click
    themeToggle.addEventListener('click', () => {
        const currentTheme = document.documentElement.getAttribute('data-bs-theme');
        if (currentTheme === 'dark') {
            document.documentElement.setAttribute('data-bs-theme', 'light');
            localStorage.setItem('theme', 'light');
            sunIcon.classList.add('d-none');
            moonIcon.classList.remove('d-none'); // Show moon in light mode
        } else {
            document.documentElement.setAttribute('data-bs-theme', 'dark');
            localStorage.setItem('theme', 'dark');
            sunIcon.classList.remove('d-none'); // Show sun in dark mode
            moonIcon.classList.add('d-none');
        }
    });
});
