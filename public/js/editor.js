/* js/editor.js
   Простой WYSIWYG: bold, italic, link, unlink, HTML view, paste sanitization, form integration.
   Требует Bootstrap 5 (для классов кнопок).
*/

function initWysiwygEditor() {
  // Настройки
  const allowedTags = ['B','STRONG','I','EM','A','BR','P','DIV','SPAN']; // разрешаем только базовые теги
  const editorId = 'comment_editor';
  const codeId = 'comment_code';
  const textareaId = 'comment'; // НЕ МЕНЯТЬ
  const toggleHtmlBtnId = 'toggle-code';

  const editor = document.getElementById(editorId);
  const codeArea = document.getElementById(codeId);
  const textarea = document.getElementById(textareaId);
  const toggleBtn = document.getElementById(toggleHtmlBtnId);
  const toolbar = document.querySelector('.wysiwyg-toolbar');

  if (!editor) {
    console.error('WYSIWYG: не найден элемент редактора с id', editorId);
    return;
  }
  if (!textarea) {
    console.error('WYSIWYG: не найден textarea с id', textareaId);
    return;
  }
  if (!toolbar) {
    console.error('WYSIWYG: не найдена панель инструментов с классом wysiwyg-toolbar');
    return;
  }
  
  console.log('WYSIWYG: редактор инициализирован', { editor, textarea, toolbar });

  // Инициализация: заполнить редактор содержимым textarea (если есть)
  editor.innerHTML = textarea.value || '';

  // Скопировать атрибуты required/minlength/maxlength если нужно (мы будем валидировать вручную)
  const attrMin = textarea.getAttribute('minlength');
  const attrMax = textarea.getAttribute('maxlength');
  const attrReq = textarea.hasAttribute('required');

  // Убираем видимость оригинального textarea, но оставляем в DOM
  textarea.style.display = 'none';

  // --- Функции утилиты ---

  // Простая санитизация HTML: оставляем только allowedTags и атрибут href для A
  function sanitizeHTML(html) {
    // Создаём контейнер и парсим
    const frag = document.createElement('div');
    frag.innerHTML = html;

    function clean(node) {
      // Проверяем, что узел всё ещё находится в DOM
      if (!node || !node.parentNode) return;
      
      // Пройти рекурсивно по копии коллекции дочерних узлов
      // чтобы избежать проблем при изменении DOM во время итерации
      const children = Array.from(node.childNodes);
      for (const ch of children) {
        // Пропускаем текстовые узлы
        if (ch.nodeType === Node.TEXT_NODE) {
          continue;
        }
        
        // Обрабатываем только элементы
        if (ch.nodeType === Node.ELEMENT_NODE) {
          // Проверяем, что узел всё ещё в DOM
          if (!ch.parentNode) continue;
          
          const tag = ch.tagName.toUpperCase();
          
          // Если тег не в списке разрешённых
          if (!allowedTags.includes(tag)) {
            // Создаём фрагмент с содержимым тега
            const inner = document.createDocumentFragment();
            while (ch.firstChild) {
              inner.appendChild(ch.firstChild);
            }
            
            // Заменяем тег на его содержимое
            try {
              if (ch.parentNode) {
                ch.parentNode.replaceChild(inner, ch);
                // Продолжаем с родительского узла, так как DOM изменился
                clean(node);
                break;
              }
            } catch (e) {
              console.error('Ошибка при замене тега:', e);
              continue;
            }
          } else {
            // Разрешённый тег - очищаем атрибуты
            const attrs = Array.from(ch.attributes || []);
            for (const at of attrs) {
              // Для ссылок оставляем только безопасные атрибуты
              if (ch.tagName.toUpperCase() === 'A') {
                if (at.name !== 'href' && at.name !== 'target' && at.name !== 'rel') {
                  ch.removeAttribute(at.name);
                }
              } else {
                ch.removeAttribute(at.name);
              }
            }
            
            // Обработка ссылок
            if (ch.tagName.toUpperCase() === 'A') {
              const href = ch.getAttribute('href') || '';
              if (/^\s*javascript:/i.test(href)) {
                ch.removeAttribute('href');
              } else {
                ch.setAttribute('rel', 'noopener noreferrer');
                ch.setAttribute('target', '_blank');
              }
            }
            
            // Рекурсивная очистка дочерних элементов
            if (ch.childNodes.length > 0) {
              clean(ch);
            }
          }
        } else if (ch.parentNode) {
          // Удаляем другие типы узлов, если они всё ещё в DOM
          try {
            ch.parentNode.removeChild(ch);
          } catch (e) {
            console.error('Ошибка при удалении узла:', e);
          }
        }
      }
    }

    clean(frag);
    // Удалим пустые <div> которые только оборачивают текст? Оставим как есть — браузер корректно покажет.
    return frag.innerHTML;
  }

  // Возвращает plain text (видимый текст) из editor
  function getEditorPlainText() {
    return editor.innerText.replace(/\u00A0/g, ' ').trim();
  }

  // Проверка валидации по видимому тексту
  function validateEditor() {
    const txt = getEditorPlainText();
    if (attrReq && txt.length === 0) {
      textarea.setCustomValidity('Пожалуйста, заполните поле.');
      return false;
    }
    if (attrMin && txt.length < Number(attrMin)) {
      textarea.setCustomValidity(`Минимум ${attrMin} символов (сейчас ${txt.length}).`);
      return false;
    }
    if (attrMax && txt.length > Number(attrMax)) {
      textarea.setCustomValidity(`Максимум ${attrMax} символов (сейчас ${txt.length}).`);
      return false;
    }
    textarea.setCustomValidity('');
    return true;
  }

  // Update state кнопок (active) — использует document.queryCommandState (работает в большинстве браузеров)
  function updateToolbarState() {
    const btns = toolbar.querySelectorAll('button[data-cmd]');
    btns.forEach(btn => {
      const cmd = btn.getAttribute('data-cmd');
      if (cmd === 'createLink' || cmd === 'unlink') {
        // handled separately
        btn.classList.remove('active');
      } else {
        try {
          const state = document.queryCommandState(cmd);
          btn.classList.toggle('active', !!state);
        } catch (e) {
          btn.classList.remove('active');
        }
      }
    });
  }

  // Вставить HTML в текущую позицию курсора (вставляем sanitized html)
  function insertHTMLAtCursor(html) {
    console.log('WYSIWYG: вставка HTML', { before: editor.innerHTML, html });
    // modern: use execCommand as simple reliable way
    document.execCommand('insertHTML', false, html);
    console.log('WYSIWYG: после вставки', { after: editor.innerHTML });
  }

  // --- Обработчики тулбара ---
  toolbar.addEventListener('click', function (e) {
    console.log('WYSIWYG: клик по тулбару', e.target);
    const btn = e.target.closest('button[data-cmd]');
    if (!btn) {
      console.log('WYSIWYG: клик не по кнопке с data-cmd');
      return;
    }
    const cmd = btn.getAttribute('data-cmd');
    console.log('WYSIWYG: выполнение команды', cmd);

    if (cmd === 'createLink') {
      let url = prompt('Введите URL (например https://example.com):', 'https://');
      if (!url) return;
      // если пользователь ввёл текст в виде "example.com", приведём к httpS
      if (!/^[a-zA-Z][a-zA-Z0-9+.-]*:/.test(url)) {
        url = 'https://' + url;
      }
      document.execCommand('createLink', false, url);
    } else if (cmd === 'unlink') {
      document.execCommand('unlink', false, null);
    } else {
      // bold/italic - toggle
      console.log('WYSIWYG: выполнение команды форматирования', cmd);
      const beforeHTML = editor.innerHTML;
      document.execCommand(cmd, false, null);
      console.log('WYSIWYG: результат форматирования', { 
        command: cmd, 
        before: beforeHTML, 
        after: editor.innerHTML 
      });
    }

    // обновить state кнопок
    updateToolbarState();
    editor.focus();
  });

  // Обновлять активные состояния кнопок при изменении выделения/курсор-перемещении
  console.log('WYSIWYG: добавление обработчиков событий для редактора');
  editor.addEventListener('keyup', function(e) {
    console.log('WYSIWYG: keyup в редакторе');
    updateToolbarState();
  });
  editor.addEventListener('mouseup', function(e) {
    console.log('WYSIWYG: mouseup в редакторе');
    updateToolbarState();
  });
  editor.addEventListener('focus', function(e) {
    console.log('WYSIWYG: редактор получил фокус');
    updateToolbarState();
  });
  editor.addEventListener('blur', function(e) {
    console.log('WYSIWYG: редактор потерял фокус');
  });

  // --- Paste handling: sanitize, respect code-mode ---
  editor.addEventListener('paste', function (e) {
    e.preventDefault();

    console.log('WYSIWYG: вставка');
    console.log(e.clipboardData);
    console.log(window.clipboardData);
    console.log('-----------------------------------');

    const clipboard = (e.clipboardData || window.clipboardData);
    if (!clipboard) return;

    if (codeArea.style.display !== 'none') {
      // В режиме кода вставляем как текст
      const text = clipboard.getData('text/plain');
      document.execCommand('insertText', false, text);
      return;
    }

    // rich mode
    const html = clipboard.getData('text/html');
    const text = clipboard.getData('text/plain');

    if (html) {
      // сохраним только разрешенные теги
      const safe = sanitizeHTML(html);
      insertHTMLAtCursor(safe);
    } else if (text) {
      // вставим plain text, переводя переносы в <br>
      insertHTMLAtCursor(text);
    } else {
      // fallback
      const fallback = clipboard.getData('text');
      document.execCommand('insertText', false, fallback || '');
    }

    // обновим state
    updateToolbarState();
  });

  // --- Toggle HTML view / code view ---
  toggleBtn.addEventListener('click', function () {
    if (codeArea.style.display === 'none') {
      // переключаемся в код-режим: показать HTML
      codeArea.value = editor.innerHTML;
      editor.style.display = 'none';
      codeArea.style.display = 'block';
      codeArea.focus();
      toggleBtn.classList.add('active');
    } else {
      // из кода обратно — санитизируем HTML и покажем
      const edited = codeArea.value || '';
      const safe = sanitizeHTML(edited);
      editor.innerHTML = safe;
      codeArea.style.display = 'none';
      editor.style.display = 'block';
      editor.focus();
      toggleBtn.classList.remove('active');
    }
    updateToolbarState();
  });

  // Обработка вставки в режиме кода
  codeArea.addEventListener('paste', function (e) {
    e.preventDefault();
    
    // Получаем данные из буфера обмена
    const clipboard = (e.clipboardData || window.clipboardData || {});
    if (!clipboard) return;
    
    // Пробуем получить текст в формате plain/text
    let text = '';
    try {
      text = clipboard.getData('text/plain');
      
      // Если не удалось получить как plain/text, пробуем text
      if (!text) {
        text = clipboard.getData('text');
      }
      
      // Если все еще нет текста, выходим
      if (!text) {
        console.warn('Не удалось получить текст из буфера обмена');
        return;
      }
      
      // Сохраняем текущее выделение
      const start = this.selectionStart;
      const end = this.selectionEnd;
      const currentValue = this.value;
      
      // Вставляем текст в текущую позицию курсора
      this.value = currentValue.substring(0, start) + text + currentValue.substring(end);
      
      // Устанавливаем курсор после вставленного текста
      const newCursorPos = start + text.length;
      this.setSelectionRange(newCursorPos, newCursorPos);
      
      // Триггерим событие input для обновления состояния
      const inputEvent = new Event('input', { bubbles: true, cancelable: true });
      this.dispatchEvent(inputEvent);
      
      // Также триггерим change на случай, если есть подписчики на это событие
      const changeEvent = new Event('change', { bubbles: true, cancelable: true });
      this.dispatchEvent(changeEvent);
      
    } catch (error) {
      console.error('Ошибка при вставке текста:', error);
    }
  });
  
  // При смене фокуса в код-редакторе — можно обновлять превью (опционально)
  codeArea.addEventListener('input', function () {
    // не менять сразу editor, только при переключении обратно (чтобы не ломать код-редактирование)
  });

  // --- Перед отправкой формы: копируем html в textarea и проверяем валидацию по plain text ---
  // Найдём родительскую форму (если есть)
  function attachFormHandler() {
    let form = textarea.closest('form');
    if (!form) {
      // Попробуем обойтись: слушаем submit на document (мало шансов)
      return;
    }

    form.addEventListener('submit', function (e) {
      // Проверка видимого текста
      if (!validateEditor()) {
        // показать стандартные ошибки:
        textarea.reportValidity();
        e.preventDefault();
        return;
      }
      // Скопировать HTML в textarea
      textarea.value = sanitizeHTML(editor.innerHTML);
      // дальше форма отправится обычным способом
    });
  }
  attachFormHandler();

  // Если кто-то прямо меняет textarea (не должно), синхронизировать:
  const observer = new MutationObserver(() => {
    // если textarea.value изменили извне, обновим визуальный редактор
    if (textarea.value !== editor.innerHTML) {
      editor.innerHTML = textarea.value || '';
    }
  });

  observer.observe(textarea, { attributes: true, childList: true, characterData: true });

  // Инициализация — очистка потенциально опасного HTML в textarea при старте
  textarea.value = sanitizeHTML(textarea.value || '');
  
  // Возвращаем методы для управления редактором
  return {
    updateToolbarState: updateToolbarState,
    destroy: function() {
      observer.disconnect();
      // Здесь можно добавить отписку от других событий при необходимости
    }
  };
}

// Глобальная переменная для хранения экземпляра редактора
let wysiwygEditorInstance = null;

// Функция для сброса редактора
function resetWysiwygEditor() {
  const container = document.querySelector('.my-wysiwyg');
  if (!container) {
    console.error('WYSIWYG: контейнер редактора не найден');
    return null;
  }
  
  // Сохраняем HTML контейнера
  const containerHTML = `
    <!-- Начало блока WYSIWYG -->
    <div class="my-wysiwyg">
      <!-- Панель кнопок -->
      <div class="wysiwyg-toolbar btn-group mb-2" role="group" aria-label="Editor toolbar">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-cmd="bold" title="Жирный"><strong>B</strong></button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-cmd="italic" title="Курсив"><em>I</em></button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-cmd="createLink" title="Вставить ссылку">link</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-cmd="unlink" title="Убрать ссылку">unlink</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="toggle-code" title="HTML">HTML</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="show-help" title="Справка">
          <i class="bi bi-question-circle"></i>
        </button>
      </div>

      <!-- Визуальный редактор -->
      <div class="wysiwyg-editor border rounded p-2" contenteditable="true" id="comment_editor"></div>

      <!-- Редактор HTML-кода -->
      <textarea class="wysiwyg-code form-control mt-2" id="comment_code" rows="6" style="display:none;"></textarea>

      <!-- Оригинальный textarea (скрытый) -->
      <textarea class="form-control" id="comment" name="comment" rows="3" 
                placeholder="Введите комментарий к заявке" required minlength="3"
                maxlength="1000" style="display:none;"></textarea>
      <!-- Сообщение об ошибке -->
      <div id="comment_error" class="invalid-feedback d-none">
        Пожалуйста, введите комментарий (от 3 до 1000 символов)
      </div>
    </div>
  `;
  
  // Полностью заменяем содержимое контейнера
  container.outerHTML = containerHTML;
  
  console.log('WYSIWYG: редактор полностью пересоздан');
  
  // Возвращаем новый элемент редактора
  return document.getElementById('comment_editor');
}

// Функция для уничтожения редактора
function destroyWysiwygEditor() {
  // Уничтожаем экземпляр редактора, если он существует
  if (wysiwygEditorInstance) {
    if (typeof wysiwygEditorInstance.destroy === 'function') {
      wysiwygEditorInstance.destroy();
    }
    wysiwygEditorInstance = null;
  }
  
  // Полностью пересоздаем редактор
  const newEditor = resetWysiwygEditor();
  
  console.log('WYSIWYG: редактор уничтожен и пересоздан');
  
  // Возвращаем новый элемент редактора
  return newEditor;
}

// Модифицируем initWysiwygEditor для возврата методов управления
const originalInitWysiwygEditor = initWysiwygEditor;
window.initWysiwygEditor = function() {
  // Если редактор уже инициализирован, сначала уничтожаем его
  destroyWysiwygEditor();
  
  // Инициализируем новый экземпляр редактора
  wysiwygEditorInstance = originalInitWysiwygEditor();
  return wysiwygEditorInstance;
};

// Добавляем глобальные методы для управления редактором
window.resetWysiwygEditor = resetWysiwygEditor;
window.destroyWysiwygEditor = destroyWysiwygEditor;
