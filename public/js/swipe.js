console.log('Скрипт swipe.js загружен');

// Desktop View Toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM полностью загружен, инициализируем toggleDesktopView');
    toggleDesktopView();
});

function toggleDesktopView() {
    console.log('Вызов функции toggleDesktopView');
    const desktopViewToggle = document.getElementById('toggle-desktop-view');
    const desktopViewCSS = document.getElementById('desktop-view-css');
    
    console.log('Элементы управления:', { desktopViewToggle, desktopViewCSS });
    
    if (!desktopViewToggle || !desktopViewCSS) return;
    
    // Check saved preference
    const isDesktopView = localStorage.getItem('desktopView') === 'true';
    
    function updateDesktopView(enable) {
        console.log('Изменение режима отображения на:', enable ? 'десктопный' : 'мобильный');
        
        if (enable) {
            console.log('Включение десктопного режима');
            desktopViewCSS.removeAttribute('disabled');
            desktopViewToggle.classList.add('active');
            initSwipe();
        } else {
            console.log('Отключение десктопного режима');
            desktopViewCSS.setAttribute('disabled', 'disabled');
            desktopViewToggle.classList.remove('active');
            removeSwipeListeners();
        }
        
        localStorage.setItem('desktopView', enable);
        console.log('Текущее состояние в localStorage:', localStorage.getItem('desktopView'));
    }
    
    // Initialize based on saved preference
    updateDesktopView(isDesktopView);
    
    // Toggle on button click
    desktopViewToggle.addEventListener('click', (event) => {
        console.log('Нажата кнопка переключения режима');
        console.log('Текущее состояние кнопки:', event.target);
        const isCurrentlyEnabled = !desktopViewCSS.hasAttribute('disabled');
        console.log('Текущий режим:', isCurrentlyEnabled ? 'десктопный' : 'мобильный');
        console.log('Будет установлен режим:', isCurrentlyEnabled ? 'мобильный' : 'десктопный');
        updateDesktopView(!isCurrentlyEnabled);
    });
    
    // Swipe functionality
    let touchStartX = 0;
    let touchStartY = 0;
    const minSwipeDistance = 50;
    
    function handleTouchStart(e) {
        console.log('Начало касания');
        touchStartX = e.touches[0].clientX;
        touchStartY = e.touches[0].clientY;
        document.body.classList.add('swiping');
        console.log('Начальные координаты:', { x: touchStartX, y: touchStartY });
    }
    
    function handleTouchMove(e) {
        if (!touchStartX) return;
        
        const touchX = e.touches[0].clientX;
        const touchY = e.touches[0].clientY;
        const diffX = touchStartX - touchX;
        const diffY = touchStartY - touchY;

        if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > minSwipeDistance) {
            e.preventDefault();
        }
    }
    
    function handleTouchEnd(e) {
        if (!touchStartX) {
            console.log('Нет начальных координат для свайпа');
            return;
        }
        
        const touchEndX = e.changedTouches[0].clientX;
        const touchEndY = e.changedTouches[0].clientY;
        
        const diffX = touchStartX - touchEndX;
        const diffY = touchStartY - touchEndY;
        
        console.log('Конец касания', { 
            start: { x: touchStartX, y: touchStartY },
            end: { x: touchEndX, y: touchEndY },
            diff: { x: diffX, y: diffY },
            isHorizontalSwipe: Math.abs(diffX) > Math.abs(diffY),
            isEnoughDistance: Math.abs(diffX) > minSwipeDistance
        });
        
        if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > minSwipeDistance) {
            const activeTab = document.querySelector('[data-bs-target^="#tab"].active');
            if (!activeTab) return;
            
            if (diffX > 0 && activeTab.nextElementSibling) {
                activeTab.nextElementSibling.click();
            } else if (diffX < 0 && activeTab.previousElementSibling) {
                activeTab.previousElementSibling.click();
            }
        }
        
        touchStartX = 0;
        touchStartY = 0;
        document.body.classList.remove('swiping');
    }
    
    function initSwipe() {
        document.addEventListener('touchstart', handleTouchStart, { passive: true });
        document.addEventListener('touchmove', handleTouchMove, { passive: false });
        document.addEventListener('touchend', handleTouchEnd, { passive: true });
    }
    
    function removeSwipeListeners() {
        document.removeEventListener('touchstart', handleTouchStart);
        document.removeEventListener('touchmove', handleTouchMove);
        document.removeEventListener('touchend', handleTouchEnd);
    }    
}


