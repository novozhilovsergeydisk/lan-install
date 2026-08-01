(function() {
    /**
     * Динамическая фиксация панели управления и шапки таблицы при прокрутке.
     *
     * Работает на двух вкладках по одному механизму:
     *   «Планирование» — .planning-sticky-toolbar + #requestsPlanningTable
     *   «Заявки»       — .requests-sticky-toolbar + #requestsTable
     *
     * Панель прилипает к верху экрана, шапка таблицы — под панелью.
     */
    const navbarHeight = 56;

    // Конфигурация вкладок: свой стартовый offset у каждой (считается лениво при скролле).
    // alignToParent — привязывать левый край и ширину к контейнеру (иначе панель уезжает
    // к краю экрана и не совпадает с таблицей). У планирования своя вёрстка с #planning-content,
    // там ширина уже задана в CSS и подгонять не нужно.
    const targets = [
        { toolbar: '.planning-sticky-toolbar', tableId: 'requestsPlanningTable', tabId: 'planning', initialTop: 0, alignToParent: false },
        { toolbar: '.requests-sticky-toolbar', tableId: 'requestsTable', tabId: 'requests', initialTop: 0, alignToParent: true },
    ];

    function initSticky() {
        targets.forEach(cfg => {
            const toolbar = document.querySelector(cfg.toolbar);
            const table = document.getElementById(cfg.tableId);
            const thead = table ? table.querySelector('thead') : null;
            const tab = document.getElementById(cfg.tabId);

            if (!toolbar) return;

            function onScroll() {
                // Фиксируем только на активной вкладке, иначе панели «всплывали» бы поверх чужого контента.
                if (!tab || !tab.classList.contains('active')) return;

                const currentScroll = window.scrollY || document.documentElement.scrollTop;

                if (cfg.initialTop <= 0 && !toolbar.classList.contains('is-sticky')) {
                    const rect = toolbar.getBoundingClientRect();
                    cfg.initialTop = rect.top + window.scrollY;
                }

                // Фиксация тулбара
                if (cfg.initialTop > 0 && (currentScroll >= cfg.initialTop)) {
                    if (!toolbar.classList.contains('is-sticky')) {
                        // Запоминаем геометрию контейнера ДО фиксации: position:fixed выдёргивает
                        // элемент из потока, и без этого панель уезжает к краю экрана,
                        // не совпадая по левой границе с таблицей под ней.
                        const parentRect = toolbar.parentElement.getBoundingClientRect();
                        toolbar.classList.add('is-sticky');
                        toolbar.parentElement.style.paddingTop = toolbar.offsetHeight + 'px';

                        if (cfg.alignToParent) {
                            toolbar.style.left = parentRect.left + 'px';
                            toolbar.style.width = parentRect.width + 'px';
                        }
                    }
                } else {
                    if (toolbar.classList.contains('is-sticky')) {
                        toolbar.classList.remove('is-sticky');
                        toolbar.parentElement.style.paddingTop = '0';
                        toolbar.style.left = '';
                        toolbar.style.width = '';
                        cfg.initialTop = 0;
                    }
                }

                // Фиксация шапки таблицы под тулбаром
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
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSticky);
    } else {
        initSticky();
    }

    // При переключении вкладок сбрасываем запомненные позиции — вёрстка другая.
    document.addEventListener('shown.bs.tab', function() {
        targets.forEach(cfg => { cfg.initialTop = 0; });
        initSticky();
    });
})();
