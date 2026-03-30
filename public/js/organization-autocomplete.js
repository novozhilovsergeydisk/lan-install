/**
 * Обработчик автозаполнения для поля "Организация" в формах создания и редактирования заявки.
 */
document.addEventListener('DOMContentLoaded', () => {
    console.log('Organization autocomplete script initialized');
    
    // Список ID инпутов, для которых нужно включить автозаполнение
    const inputIds = ['clientOrganization', 'editClientOrganization'];
    
    let organizations = [];
    let loadPromise = null;
    
    // Загружаем список организаций один раз (лениво)
    const loadOrganizations = () => {
        if (organizations.length > 0) return Promise.resolve(organizations);
        if (loadPromise) return loadPromise;
        
        console.log('Loading organizations list from server...');
        loadPromise = fetch('/reports/organizations')
            .then(response => {
                if (response.ok) {
                    return response.json();
                }
                throw new Error(`Failed to load: ${response.status} ${response.statusText}`);
            })
            .then(data => {
                organizations = data.map(item => item.organization).filter(Boolean);
                console.log(`Successfully loaded ${organizations.length} organizations`);
                return organizations;
            })
            .catch(error => {
                console.error('Error fetching organizations:', error);
                loadPromise = null; // Позволяем повторную попытку при ошибке
                return [];
            });
            
        return loadPromise;
    };

    inputIds.forEach(id => {
        const orgInput = document.getElementById(id);
        if (!orgInput) {
            console.warn(`Input with id "${id}" not found in DOM`);
            return;
        }

        console.log(`Setting up autocomplete for input #${id}`);

        // Проверяем, не обернут ли уже
        if (orgInput.parentElement.classList.contains('autocomplete-container')) {
            console.log(`#${id} is already wrapped`);
            return;
        }

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
            console.log(`Searching for "${val}" in ${organizations.length} organizations`);
            const filtered = organizations.filter(org => 
                org.toLowerCase().includes(val.toLowerCase())
            ).slice(0, 15);

            if (filtered.length === 0) {
                console.log('No matches found');
                resultsContainer.classList.add('d-none');
                return;
            }

            resultsContainer.innerHTML = '';
            filtered.forEach((org, index) => {
                const item = document.createElement('div');
                item.className = 'autocomplete-item';
                item.textContent = org;
                item.addEventListener('click', () => {
                    console.log(`Selected: ${org}`);
                    orgInput.value = org;
                    resultsContainer.classList.add('d-none');
                    activeIndex = -1;
                    // Генерируем событие input
                    orgInput.dispatchEvent(new Event('input', { bubbles: true }));
                });
                resultsContainer.appendChild(item);
            });

            resultsContainer.classList.remove('d-none');
            activeIndex = -1;
            console.log(`Displayed ${filtered.length} suggestions`);
        };

        orgInput.addEventListener('focus', () => {
            loadOrganizations();
        });

        orgInput.addEventListener('input', (e) => {
            const val = e.target.value;
            console.log(`Input event on #${id}: "${val}"`);
            
            if (val.length < 2) {
                resultsContainer.classList.add('d-none');
                return;
            }
            
            loadOrganizations().then(() => {
                if (val.length >= 2 && document.activeElement === orgInput) {
                    showResults(val);
                }
            });
        });

        // Навигация клавишами
        orgInput.addEventListener('keydown', (e) => {
            if (resultsContainer.classList.contains('d-none')) return;
            const items = resultsContainer.querySelectorAll('.autocomplete-item');

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

        // Закрытие при клике вне
        document.addEventListener('click', (e) => {
            if (!wrapper.contains(e.target)) {
                resultsContainer.classList.add('d-none');
            }
        });
    });
});
