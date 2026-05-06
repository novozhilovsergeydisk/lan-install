# Карта форм и обработчиков проекта lan-install.online

## Основные формы и их обработчики

### 1. Аутентификация

| Форма | Шаблон | Метод | Маршрут | Контроллер | JS-обработчик |
|-------|---------|------|----------|------------|---------------|
| Вход в систему | `resources/views/auth/login.blade.php` | POST | `/login` | `Auth\LoginController@login` | - |
| Регистрация | `resources/views/auth/register.blade.php` | POST | `/register` | `Auth\RegisterController@register` | - |

### 2. Заявки

| Форма | Шаблон | Метод | Маршрут | Контроллер | JS-обработчик |
|-------|---------|------|----------|------------|---------------|
| Новая заявка | `welcome.blade.php` / `welcome-new.blade.php` (модальное окно `newRequestModal`) | POST | `/api/requests` | `HomeController@storeRequest` | `handler.js:initializePage()`, `handler.js:initAllCustomSelects()` |
| Редактирование заявки | `welcome.blade.php` (модальное окно `editRequestModal`) | PUT | `/requests/{id}` | `HomeController@updateRequest` | `form-handlers.js:initEditRequestHandler()`, `form-handlers.js:initEditRequestFormHandler()` |
| Закрытие заявки | (кнопка `.close-request-btn`) | POST | `/requests/{request}/close` | `HomeController@closeRequest` | `requests.js:closeRequest()` |
| Открытие заявки | (кнопка `.open-request-btn`) | POST | `/requests/{request}/open` | `HomeController@openRequest` | `form-handlers.js:initOpenRequestHandler()` |
| Дополнительное задание | (модальное окно `additionalTaskModal`) | POST | `/api/requests` | `HomeController@storeRequest` | `modals.js:initAdditionalTaskModal()` |
| Отмена заявки | (кнопка `.cancel-request-btn`) | POST | `/requests/cancel` | `HomeController@cancelRequest` | `handler.js:initializePage()` |
| Перенос заявки | (кнопка `.transfer-request-btn`) | POST | `/api/requests/transfer` | `HomeController@transferRequest` | `handler.js:initializePage()` |
| Назначение бригады | (модальное окно `assign-team-modal`) | POST | `/api/requests/update-brigade` или `/api/requests/update-brigade-mass` | `ControllerRequestModification@updateRequestBrigade` | `init-handlers.js` (обработчик `confirm-assign-team-btn`) |
| Добавление комментария | (модальное окно `commentsModal`, форма `addCommentForm`) | POST | `/requests/comment` | `HomeController@addComment` | `modals.js` |
| Редактирование комментария | (кнопка редактирования в модальном окне) | PUT | `/api/comments/{id}` | `HomeController@updateComment` | `form-handlers.js:initCommentEditHandlers()` |
| Загрузка фотоотчета | (модальное окно `addPhotoModal`) | POST | `/api/requests/photo-report` | `HomeController@uploadPhotoReport` | `modals.js:initPhotoReportModal()` |
| Загрузка фото к комментарию | (форма в модальном окне) | POST | `/api/requests/photo-comment` | `HomeController@uploadPhotoComment` | `modals.js` |
| Планирование заявок | (вкладка "Планирование") | POST | `/planning-requests` | `PlanningRequestController@store` | `form-handlers.js:initPlanningRequestFormHandlers()` |
| Загрузка Excel с заявками | (форма в модальном окне) | POST | `/planning-requests/upload-excel` | `PlanningRequestController@uploadRequestsExcel` | `form-handlers.js:initUploadRequestsHandler()` |
| Изменение статуса планирования | (кнопки в таблице планирования) | POST | `/change-planning-request-status` | `PlanningRequestController@changePlanningRequestStatus` | `handler.js:loadPlanningRequests()` |
| Массовое назначение бригады | (кнопка `btn-mass-assign-team`) | POST | `/api/requests/update-brigade-mass` | `ControllerRequestModification@updateRequestBrigadeMass` | `init-handlers.js` |

### 3. Бригады

| Форма | Шаблон | Метод | Маршрут | Контроллер | JS-обработчик |
|-------|---------|------|----------|------------|---------------|
| Создание бригады | `welcome.blade.php` (форма `brigadeForm`) | POST | `/brigades` | `BrigadeController@store` | `handler.js:handlerCreateBrigade()` |
| Добавление сотрудника в бригаду | (кнопка `addToBrigadeBtn`) | - | - | - | `handler.js:hanlerAddToBrigade()` |
| Удаление члена бригады | (кнопка `.delete-member-btn`) | POST | `/brigade/delete/{id}` | `BrigadeController@deleteBrigade` | `form-handlers.js:initDeleteMember()` |
| Просмотр бригады | (кнопка `.view-brigade-btn`, модальное окно `brigadeModal`) | POST | `/brigade/{id}` | `BrigadeController@getBrigadeData` | `brigades.js:showBrigadeDetails()` |

### 4. Адреса

| Форма | Шаблон | Метод | Маршрут | Контроллер | JS-обработчик |
|-------|---------|------|----------|------------|---------------|
| Добавление адреса | (модальное окно `assignAddressModal`, форма `addressForm`) | POST | `/api/addresses/add` | `GeoController@addAddress` | `handler.js`, `address-documents.js:initAddressDocumentUpload()` |
| Редактирование адреса | (модальное окно `editAddressModal`) | PUT | `/api/addresses/{id}` | `GeoController@updateAddress` | `form-handlers.js:initAddressEditHandlers()`, `form-handlers.js:initAddressEditButton()` |
| Удаление адреса | (кнопки в списке дубликатов) | DELETE | `/api/addresses/{id}` | `GeoController@deleteAddress` | `addresses.js:showDuplicatesModal()` |
| Добавление города | (модальное окно `assignCityModal`, форма `addCityForm`) | POST | `/cities/store` | `CityController@store` | `form-handlers.js:initAddCity()` |
| Загрузка Excel с адресами | (форма `uploadExcelForm`) | POST | `/api/requests/upload-excel` | `CommentPhotoController@uploadExcel` | `init-handlers.js` |
| Загрузка документа адреса | (инпут `addressDocument` / `editAddressDocument`) | POST | `/api/address-documents` | `AddressDocumentController@store` | `address-documents.js:initAddressDocumentHandlers()` |

### 5. Сотрудники

| Форма | Шаблон | Метод | Маршрут | Контроллер | JS-обработчик |
|-------|---------|------|----------|------------|---------------|
| Новый сотрудник | (модальное окно `newEmployeeModal`, форма `employeeForm`) | POST | `/employees/store` | `EmployeeUserController@store` | `handler.js:handlerAddEmployee()` |
| Редактирование сотрудника | (форма в строке таблицы) | POST | `/employee/update` | `EmployeeUserController@update` | `form-handlers.js:initEmployeeEditHandlers()`, `form-handlers.js:initSaveEmployeeChanges()` |
| Фильтр сотрудников | (селект `employeeFilter`) | POST | `/api/employees/filter` | `EmployeesFilterController@filterByDate` | `form-handlers.js:initEmployeeFilter()` |
| Удаление сотрудника | (кнопка `.delete-employee-btn`) | POST | `/employee/delete` | `EmployeeUserController@deleteEmployee` | `form-handlers.js:initDeleteEmployee()` |
| Восстановление сотрудника | (кнопка `.restore-employee-btn`) | POST | `/employee/restore` | `HomeController@restoreEmployee` | `init-handlers.js` |
| Экспорт сотрудников | (модальное окно `exportEmployeesModal`) | POST | `/employee/export` | `EmployeeUserController@exportEmployees` | `employee-export.js:EmployeeExporter` |
| Загрузка документа сотрудника | (модальное окно `uploadEmployeeDocumentModal`) | POST | `/api/employee-documents` | `EmployeeDocumentController@store` | `address-documents.js` (аналогично) |

### 6. Типы заявок (админ)

| Форма | Шаблон | Метод | Маршрут | Контроллер | JS-обработчик |
|-------|---------|------|----------|------------|---------------|
| Добавление типа заявки | (модальное окно `addRequestTypeModal`, форма `requestTypeForm`) | POST | `/api/request-types/` | `RequestTypeController@store` | `request-types.js:initRequestTypesHandlers()` |
| Редактирование типа заявки | (кнопка `.edit-request-type-btn`) | PUT | `/api/request-types/{id}/` | `RequestTypeController@update` | `request-types.js:handleEditRequestType()` |
| Удаление типа заявки | (кнопка `.delete-request-type-btn`) | DELETE | `/api/request-types/{id}/` | `RequestTypeController@destroy` | `request-types.js` |

### 7. Параметры типов заявок (админ)

| Форма | Шаблон | Метод | Маршрут | Контроллер | JS-обработчик |
|-------|---------|------|----------|------------|---------------|
| Добавление параметра | (модальное окно `addWorkParameterTypeModal`) | POST | `/api/work-parameter-types/` | `WorkParameterTypeController@store` | `work-parameter-types.js:initWorkParameterTypesHandlers()` |
| Редактирование параметра | (кнопка `.edit-work-parameter-type-btn`) | PUT | `/api/work-parameter-types/{id}/` | `WorkParameterTypeController@update` | `work-parameter-types.js` |
| Удаление параметра | (кнопка `.delete-work-parameter-type-btn`) | DELETE | `/api/work-parameter-types/{id}/` | `WorkParameterTypeController@destroy` | `work-parameter-types.js` |

### 8. Отчеты

| Форма | Шаблон | Метод | Маршрут | Контроллер | JS-обработчик |
|-------|---------|------|----------|------------|---------------|
| Отчет по датам | (вкладка "Отчеты") | POST | `/reports/requests/by-date` | `ReportController@getRequestsByDateRange` | `report-handler.js:initReportHandlers()` |
| Отчет по сотруднику и датам | (вкладка "Отчеты") | POST | `/reports/requests/by-employee-date` | `ReportController@getRequestsByEmployeeAndDateRange` | `report-handler.js` |
| Отчет по адресу и датам | (вкладка "Отчеты") | POST | `/reports/requests/by-address-date` | `ReportController@getRequestsByAddressAndDateRange` | `report-handler.js` |
| Отчет за все периоды | (вкладка "Отчеты") | POST | `/reports/requests/all-period` | `ReportController@getAllPeriod` | `report-handler.js` |
| Экспорт отчета | (кнопка `export-report-btn`) | POST | `/reports/requests/export` | `ReportController@export` | `report-export.js` |
| Печать допуска | (кнопка `btn-print-work-permit`) | GET | `/reports/work-permit` | `ReportController@printWorkPermit` | `print-work-permit.js` |

### 9. WMS интеграция (админ)

| Форма | Шаблон | Метод | Маршрут | Контроллер | JS-обработчик |
|-------|---------|------|----------|------------|---------------|
| Создание привязки WMS | (вкладка "WMS склады", форма `wms-mapping`) | POST | `/wms-mappings` | `WmsMappingController@store` | `wms-mapping-settings.js:initWmsMappingSettings()` |
| Удаление привязки WMS | (кнопка удаления) | DELETE | `/wms-mappings/{id}` | `WmsMappingController@destroy` | `wms-mapping-settings.js` |

### 10. Карта и календарь

| Элемент | Шаблон | Действие | JS-обработчик |
|---------|---------|----------|---------------|
| Кнопка "На карте" | `welcome.blade.php` | Открытие/закрытие карты Яндекс | `map-requests.js:setupMapControls()` |
| Чекбокс "Планирование" | `welcome.blade.php` | Показ заявок планирования на карте | `map-requests.js` |
| Кнопка "Календарь" | `welcome.blade.php` | Открытие календаря | `handler.js:initializePage()` |
| Календарь отчетов | (вкладка "Отчеты") | Выбор дат для отчетов | `report-handler.js:initReportDatepickers()` |

## JS файлы и их основные функции

| Файл | Основные функции |
|------|-------------------|
| `public/js/init-handlers.js` | Главный файл инициализации, вызывает все остальные обработчики |
| `public/js/form-handlers.js` | Обработчики форм: редактирование заявки, открытие, удаление, фотоотчеты, комментарии, города, сотрудники, планирование |
| `public/js/handler.js` | `initializePage()`, `initTooltips()`, `initRequestButtons()`, `initAllCustomSelects()`, `handlerCreateBrigade()`, `hanlerAddToBrigade()`, `handlerAddEmployee()`, `loadAddresses()`, `loadPlanningRequests()`, `linkifyRenderedComments()` |
| `public/js/requests.js` | `closeRequest()` |
| `public/js/brigades.js` | `loadBrigades()`, `showBrigadeDetails()`, `showCreateBrigadeModal()`, `saveBrigade()` |
| `public/js/teams.js` | `initTeamForm()`, `loadEmployees()`, `handleCreateTeam()` |
| `public/js/addresses.js` | `checkForDuplicateAddresses()`, `showDuplicatesModal()` |
| `public/js/request-types.js` | `loadRequestTypes()`, `createRequestType()`, `updateRequestType()`, `deleteRequestType()`, `initRequestTypesHandlers()` |
| `public/js/work-parameter-types.js` | `loadWorkParameterTypes()`, `createWorkParameterType()`, `updateWorkParameterType()`, `deleteWorkParameterType()`, `initWorkParameterTypesHandlers()` |
| `public/js/comments.js` | `loadComments()`, `updateCommentsBadge()` |
| `public/js/address-documents.js` | `initAddressDocumentHandlers()`, `uploadAddressDocuments()`, `loadAddressDocuments()` |
| `public/js/modals.js` | `initAdditionalTaskModal()`, `initPhotoReportModal()` |
| `public/js/report-handler.js` | `initReportHandlers()`, `initReportDatepickers()`, `renderReportTable()` |
| `public/js/report-export.js` | Обработчик экспорта отчетов |
| `public/js/wms-mapping-settings.js` | `initWmsMappingSettings()` |
| `public/js/map-requests.js` | `setupMapControls()`, `openMap()`, `loadAndDrawRequests()` |
| `public/js/employee-export.js` | `EmployeeExporter` (класс для экспорта сотрудников) |
| `public/js/print-work-permit.js` | Печать допуска на работу |
| `public/js/organization-autocomplete.js` | Автозаполнение организаций |
| `public/js/validators/house-number-validator.js` | Валидация номера дома |

## Контроллеры и их методы

| Контроллер | Методы |
|------------|--------|
| `HomeController` | `index()`, `indexNew()`, `storeRequest()`, `updateRequest()`, `getEditRequest()`, `closeRequest()`, `openRequest()`, `deleteRequest()`, `transferRequest()`, `cancelRequest()`, `addComment()`, `updateComment()`, `getComments()`, `uploadPhotoReport()`, `uploadPhotoComment()`, `getPhotoReport()`, `getRequests()`, `getRequestsByDate()`, `getRequestCountsByMonth()`, `getRequestStatuses()`, `getAddresses()`, `getAddressesPaginated()`, `getEmployees()`, `getOperators()`, `getCities()`, `getCurrentBrigades()`, `updateCredentials()`, `restoreEmployee()`, `deleteEmployeePermanently()`, `getCommentHistory()` |
| `BrigadeController` | `create()`, `store()`, `getBrigadesByDate()`, `getBrigadeData()`, `deleteBrigade()`, `index()`, `getCurrentDayBrigades()` |
| `GeoController` | `getAddressesYandex()`, `getAddresses()`, `getAddressesPaginated()`, `getDuplicateAddresses()`, `getAddress()`, `addAddress()`, `updateAddress()`, `deleteAddress()`, `getCities()`, `getRegions()` |
| `ReportController` | `getAddresses()`, `getEmployees()`, `getOrganizations()`, `showAddressReports()`, `showAddressReportsHistory()`, `getAllPeriod()`, `getAllPeriodByEmployee()`, `getAllPeriodByAddress()`, `getAllPeriodByEmployeeAndAddress()`, `getRequestsByDateRange()`, `getRequestsByEmployeeAndDateRange()`, `getRequestsByAddressAndDateRange()`, `printWorkPermit()`, `export()` |
| `RequestTypeController` | `index()`, `store()`, `update()`, `destroy()` |
| `WorkParameterTypeController` | `index()`, `getByRequestType()`, `store()`, `update()`, `destroy()` |
| `EmployeeUserController` | `store()`, `update()`, `getEmployee()`, `filterEmployee()`, `deleteEmployee()`, `exportEmployees()` |
| `CommentPhotoController` | `index()`, `uploadExcel()`, `uploadPhotoReport()`, `downloadAllPhotos()`, `downloadArchiveFile()`, `getCommentFiles()` |
| `PlanningRequestController` | `store()`, `uploadRequestsExcel()`, `getPlanningRequests()`, `changePlanningRequestStatus()` |
| `ControllerRequestModification` | `getBrigadeByLeader()`, `updateRequestBrigade()`, `updateRequestBrigadeMass()` |
| `AddressDocumentController` | `store()`, `getByAddress()`, `download()` |
| `EmployeeDocumentController` | `store()`, `getByEmployee()`, `download()`, `destroy()` |
| `WmsMappingController` | `store()`, `destroy()` |
| `WmsIntegrationController` | `getWarehouseStock()`, `searchWarehouseStock()`, `getMappingByRequest()`, `getBrigadeStock()`, `getUserStock()` |
| `SystemController` | `metrics()` |
| `CityController` | `store()`, `getRegions()` |
| `RequestFilterController` | `filterByStatuses()`, `getStatuses()` |
| `RequestTeamFilterController` | `filterByTeams()`, `getBrigadeLeaders()`, `brigadesInfoCurrentDay()` |
| `EmployeesFilterController` | `filterByDate()` |
| `Auth\LoginController` | `showLoginForm()`, `login()` |
| `Auth\RegisterController` | `showRegistrationForm()`, `register()` |

## Примечания

- Все формы используют CSRF-токен через `meta[name="csrf-token"]`
- AJAX-запросы используют заголовки: `X-CSRF-TOKEN`, `Accept: application/json`
- Обработчики инициализируются в `init-handlers.js` при событии `DOMContentLoaded`
- Валидация форм происходит как на клиенте (JS), так и на сервере (Laravel)
- Для кастомных селектов используется функция `initAllCustomSelects()` из `handler.js`
- Карта Яндекс инициализируется в `form-handlers.js:initYandexMap()`
