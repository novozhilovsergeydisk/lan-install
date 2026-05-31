(function() {
    /**
     * Скрипт для динамической фиксации панели и шапки таблицы планирования.
     */
    let toolbarInitialTop = 0;
    const navbarHeight = 56;

    function initPlanningSticky() {
        const toolbar = document.querySelector('.planning-sticky-toolbar');
        const table = document.getElementById('requestsPlanningTable');
        const thead = table ? table.querySelector('thead') : null;
        const planningTab = document.getElementById('planning');
        
        if (!toolbar) return;

        function onScroll() {
            if (!planningTab || !planningTab.classList.contains('active')) return;

            const currentScroll = window.scrollY || document.documentElement.scrollTop;
            
            if (toolbarInitialTop <= 0 && !toolbar.classList.contains('is-sticky')) {
                const rect = toolbar.getBoundingClientRect();
                toolbarInitialTop = rect.top + window.scrollY;
            }

            // Фиксация тулбара
            if (toolbarInitialTop > 0 && (currentScroll >= toolbarInitialTop)) {
                if (!toolbar.classList.contains('is-sticky')) {
                    toolbar.classList.add('is-sticky');
                    toolbar.parentElement.style.paddingTop = toolbar.offsetHeight + 'px';
                }
            } else {
                if (toolbar.classList.contains('is-sticky')) {
                    toolbar.classList.remove('is-sticky');
                    toolbar.parentElement.style.paddingTop = '0';
                    toolbarInitialTop = 0;
                }
            }

            // Фиксация шапки таблицы
            if (thead) {
                const toolbarHeight = toolbar.offsetHeight;
                const tableTop = table.getBoundingClientRect().top + window.scrollY;
                
                if (currentScroll + toolbarHeight >= tableTop) {
                    thead.querySelectorAll('th').forEach(th => {
                        th.classList.add('is-sticky');
                        th.style.top = (toolbarHeight - 1) + 'px';
                    });
                } else {
                    thead.querySelectorAll('th').forEach(th => {
                        th.classList.remove('is-sticky');
                        th.style.top = '';
                    });
                }
            }
        }

        window.removeEventListener('scroll', onScroll, true);
        window.addEventListener('scroll', onScroll, true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPlanningSticky);
    } else {
        initPlanningSticky();
    }
    
    document.addEventListener('shown.bs.tab', function() {
        toolbarInitialTop = 0;
        initPlanningSticky();
    });
})();
