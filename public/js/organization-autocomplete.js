/**
 * Обработчик автозаполнения для поля "Организация" в формах создания и редактирования заявки.
 */
document.addEventListener('DOMContentLoaded', () => {
    // Список ID инпутов, для которых нужно включить автозаполнение
    const inputIds = ['clientOrganization', 'editClientOrganization'];
    
    let organizations = [];
    
    // Загружаем список организаций один раз (лениво)
    const loadOrganizations = async () => {
        if (organizations.length > 0) return;
        try {
            const response = await fetch('/reports/organizations');
            if (response.ok) {
                const data = await response.json();
                organizations = data.map(item => item.organization).filter(Boolean);
            }
        } catch (error) {
            console.error('Ошибка при загрузке организаций:', error);
        }
    };

    inputIds.forEach(id => {
        const orgInput = document.getElementById(id);
        if (!orgInput) return;

        // Обернем инпут в контейнер для позиционирования подсказок
        const wrapper = document.createElement('div');
        wrapper.className = 'autocomplete-container';
        orgInput.parentNode.insertBefore(wrapper, orgInput);
        wrapper.appendChild(orgInput);

        // Создаем контейнер для результатов
        const resultsContainer = document.createElement('div');
        resultsContainer.className = 'autocomplete-results d-none';
        wrapper.appendChild(resultsContainer);

        let activeIndex = -1;

        // Отключаем стандартный автокомплит
        orgInput.setAttribute('autocomplete', 'off');

        const showResults = (val) => {
            const filtered = organizations.filter(org => 
                org.toLowerCase().includes(val.toLowerCase())
            ).slice(0, 15);

            if (filtered.length === 0) {
                resultsContainer.classList.add('d-none');
                return;
            }

            resultsContainer.innerHTML = '';
            filtered.forEach((org, index) => {
                const item = document.createElement('div');
                item.className = 'autocomplete-item';
                item.textContent = org;
                item.addEventListener('click', () => {
                    orgInput.value = org;
                    resultsContainer.classList.add('d-none');
                    activeIndex = -1;
                    // Генерируем событие input, чтобы сработали другие слушатели (если есть)
                    orgInput.dispatchEvent(new Event('input', { bubbles: true }));
                });
                resultsContainer.appendChild(item);
            });

            resultsContainer.classList.remove('d-none');
            activeIndex = -1;
        };

        orgInput.addEventListener('focus', loadOrganizations);

        orgInput.addEventListener('input', (e) => {
            const val = e.target.value;
            if (val.length < 2) {
                resultsContainer.classList.add('d-none');
                return;
            }
            // Показываем результаты только если фокус на этом инпуте
            if (document.activeElement === orgInput) {
                showResults(val);
            }
        });

        // Навигация клавишами
        orgInput.addEventListener('keydown', (e) => {
            const items = resultsContainer.querySelectorAll('.autocomplete-item');
            if (resultsContainer.classList.contains('d-none')) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = (activeIndex + 1) % items.length;
                updateActive(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = (activeIndex - 1 + items.length) % items.length;
                updateActive(items);
            } else if (e.key === 'Enter' && activeIndex > -1) {
                e.preventDefault();
                items[activeIndex].click();
            } else if (e.key === 'Escape') {
                resultsContainer.classList.add('d-none');
            }
        });

        function updateActive(items) {
            items.forEach((item, index) => {
                if (index === activeIndex) {
                    item.classList.add('active');
                    item.scrollIntoView({ block: 'nearest' });
                } else {
                    item.classList.remove('active');
                }
            });
        }

        // Закрытие списка при клике вне его
        document.addEventListener('click', (e) => {
            if (!wrapper.contains(e.target)) {
                resultsContainer.classList.add('d-none');
            }
        });
    });
});
