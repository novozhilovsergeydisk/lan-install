// Инициализация скрипта свайпа

// Desktop View Toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    toggleDesktopView();
});

function toggleDesktopView() {
    const desktopViewToggle = document.getElementById('toggle-desktop-view');
    const desktopViewToggleContainer = document.getElementById('desktop-view-toggle-container');
    const desktopViewCSS = document.getElementById('desktop-view-css');
    
    if (!desktopViewToggle || !desktopViewToggleContainer || !desktopViewCSS) {
        return;
    }
    
    // Function to handle window resize
    function handleResize() {
        if (window.innerWidth >= 300 && window.innerWidth < 992) {
            desktopViewToggleContainer.style.display = 'block';
            desktopViewToggleContainer.style.visibility = 'visible';
        } else {
            desktopViewToggleContainer.style.display = 'none';
            desktopViewToggleContainer.style.visibility = 'hidden';
            
            if (window.innerWidth > 991) {
                localStorage.removeItem('desktopView');
                updateDesktopView(false);
            }
        }
    }
    
    // Initial check on load
    handleResize();
    
    // Add resize event listener
    window.addEventListener('resize', handleResize);
    
    // Check saved preference
    const isDesktopView = localStorage.getItem('desktopView') === 'true';
    
    function updateDesktopView(enable) {
        
        if (enable) {
            desktopViewCSS.removeAttribute('disabled');
            desktopViewToggle.classList.add('active');
            // document.querySelector('footer').style.position = 'fixed';
            initSwipe();
        } else {
            desktopViewCSS.setAttribute('disabled', 'disabled');
            desktopViewToggle.classList.remove('active');
            // document.querySelector('footer').style.removeProperty('position');
            removeSwipeListeners();
        }
        
        localStorage.setItem('desktopView', enable);
    }
    
    // Initialize based on saved preference
    updateDesktopView(isDesktopView);
    
    // Toggle on button click
    desktopViewToggle.addEventListener('click', function(event) {
        event.preventDefault();
        const isCurrentlyEnabled = !desktopViewCSS.hasAttribute('disabled');
        updateDesktopView(!isCurrentlyEnabled);
    });
    
    // Swipe functionality
    let touchStartX = 0;
    let touchStartY = 0;
    let initialTouchDistance = 0;
    let initialScale = 1;
    const minSwipeDistance = 50;
    
    // Получаем основной контейнер контента
    const content = document.querySelector('.content-wrapper') || document.body;
    
    // Инициализация модуля перемещения контента
    const contentDragger = (function() {
        let isDragging = false;
        let startX, startY, translateX = 0, translateY = 0;
        let currentScale = 1;
        
        function startDrag(e) {
            if (e.touches.length !== 1) return false;
            
            // Получаем текущий масштаб
            const transform = window.getComputedStyle(content).transform;
            if (transform !== 'none') {
                const matrix = transform.match(/^matrix\(([\d.]+)/);
                currentScale = matrix ? parseFloat(matrix[1]) : 1;
            } else {
                currentScale = 1;
            }
            
            // Если масштаб 1, перемещение не требуется
            if (currentScale <= 1) return false;
            
            isDragging = true;
            startX = e.touches[0].clientX - translateX;
            startY = e.touches[0].clientY - translateY;
            content.style.transition = 'none';
            return true;
        }
        
        function drag(e) {
            if (!isDragging) return;
            
            e.preventDefault();
            
            // Вычисляем новые координаты
            const x = e.touches[0].clientX - startX;
            const y = e.touches[0].clientY - startY;
            
            // Ограничиваем перемещение в пределах видимой области
            const maxX = (content.offsetWidth * (currentScale - 1)) / 2;
            const maxY = (content.offsetHeight * (currentScale - 1)) / 2;
            
            translateX = Math.max(-maxX, Math.min(maxX, x));
            translateY = Math.max(-maxY, Math.min(maxY, y));
            
            // Применяем трансформацию
            content.style.transform = `matrix(${currentScale}, 0, 0, ${currentScale}, ${translateX}, ${translateY})`;
        }
        
        function endDrag() {
            if (!isDragging) return;
            isDragging = false;
            content.style.transition = 'transform 0.1s ease-out';
        }
        
        function reset() {
            translateX = 0;
            translateY = 0;
            content.style.transform = `scale(${currentScale})`;
        }
        
        return {
            start: startDrag,
            drag: drag,
            end: endDrag,
            reset: reset
        };
    })();
    
    function handleTouchStart(e) {
        if (e.touches.length === 2) {
            const touch1 = e.touches[0];
            const touch2 = e.touches[1];
            initialTouchDistance = Math.hypot(
                touch2.clientX - touch1.clientX,
                touch2.clientY - touch1.clientY
            );
            
            const transform = window.getComputedStyle(content).transform;
            if (transform !== 'none') {
                const matrix = transform.match(/^matrix\(([\d.]+)/);
                initialScale = matrix ? parseFloat(matrix[1]) : 1;
            } else {
                initialScale = 1;
            }
            return;
        }
        
        // Пробуем начать перетаскивание
        const isDragging = contentDragger.start(e);
        if (!isDragging) {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
            document.body.classList.add('swiping');
        }
    }
    
    function handleTouchMove(e) {
        try {
            // Обработка жеста масштабирования двумя пальцами
            if (e.touches.length === 2) {
                e.preventDefault();
                
                const touch1 = e.touches[0];
                const touch2 = e.touches[1];
                const currentDistance = Math.hypot(
                    touch2.clientX - touch1.clientX,
                    touch2.clientY - touch1.clientY
                );
                
                // Вычисляем минимальный масштаб, чтобы контент не был уже ширины экрана
                const container = document.querySelector('.content-wrapper') || document.documentElement;
                const containerWidth = container.offsetWidth;
                const viewportWidth = window.innerWidth;
                const minScale = Math.min(1, viewportWidth / containerWidth);
                
                let scale = initialScale * (currentDistance / initialTouchDistance);
                scale = Math.min(Math.max(minScale, scale), 3);
                
                content.style.transform = `scale(${scale})`;
                content.style.transformOrigin = 'center center';
                content.style.transition = 'transform 0.1s ease-out';
                return;
            }
            
            // Обработка перемещения контента
            if (contentDragger) {
                contentDragger.drag(e);
                // Если обрабатываем перемещение, прерываем стандартную обработку свайпа
                if (window.getComputedStyle(content).transform !== 'none') {
                    e.preventDefault();
                    return;
                }
            }
            
            // Стандартная обработка свайпа для смены вкладок
            if (!touchStartX) return;
            if (!e?.touches?.[0]) return;
            
            const touchX = e.touches[0].clientX;
            const touchY = e.touches[0].clientY;
            const diffX = touchStartX - touchX;
            const diffY = touchStartY - touchY;

            const isHorizontal = Math.abs(diffX) > Math.abs(diffY);
            const isEnoughDistance = Math.abs(diffX) > minSwipeDistance;
            
            if (isHorizontal && isEnoughDistance) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        } catch (error) {
        }
    }
    
    function handleTouchEnd(e) {
        // Завершаем перетаскивание, если оно активно
        if (contentDragger) {
            contentDragger.end();
        }
        
        if (e.touches && e.touches.length === 1) {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
            return;
        }
        
        if (!touchStartX) return;
        
        const touchEndX = e.changedTouches[0].clientX;
        const touchEndY = e.changedTouches[0].clientY;
        
        const diffX = touchStartX - touchEndX;
        const diffY = touchStartY - touchEndY;
        
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
        removeSwipeListeners();
        
        const tabContent = document.querySelector('.tab-content');
        if (tabContent) {
            // Применяем начальный масштаб, чтобы контент помещался по ширине
            const container = document.querySelector('.content-wrapper') || document.documentElement;
            const containerWidth = container.offsetWidth;
            const viewportWidth = window.innerWidth;
            const initialScale = Math.min(1, viewportWidth / containerWidth);
            
            container.style.transform = `scale(${initialScale})`;
            container.style.transformOrigin = 'center center';
            
            tabContent.addEventListener('touchstart', handleTouchStart, { passive: true });
            tabContent.addEventListener('touchmove', handleTouchMove, { passive: false });
            tabContent.addEventListener('touchend', handleTouchEnd, { passive: true });
        }
    }
    
    function removeSwipeListeners() {
        document.removeEventListener('touchstart', handleTouchStart);
        document.removeEventListener('touchmove', handleTouchMove);
        document.removeEventListener('touchend', handleTouchEnd);
    }    
}


