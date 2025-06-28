document.addEventListener('DOMContentLoaded', function () {
    const button = document.getElementById('btn-open-calendar');
    const calendarContent = document.getElementById('calendar-content');

    if (button && calendarContent) {
        button.addEventListener('click', function () {
            console.log('Display', calendarContent.style.display)

            // Переключаем видимость
            if (calendarContent.classList.contains('hide-me')) {
                calendarContent.classList.remove('hide-me');
                calendarContent.classList.add('show-me');
            } else {
                calendarContent.classList.remove('show-me');
                calendarContent.classList.add('hide-me');
            }
        });
    }
});
