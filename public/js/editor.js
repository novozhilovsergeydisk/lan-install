/* js/editor.js
   Простой WYSIWYG: bold, italic, link, unlink, HTML view, paste sanitization, form integration.
   Требует Bootstrap 5 (для классов кнопок).
*/

(function () {
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

  if (!editor || !textarea || !toolbar) {
    console.warn('WYSIWYG: не найден один из обязательных элементов.');
    return;
  }

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
      // Пройти рекурсивно
      const children = Array.from(node.childNodes);
      for (const ch of children) {
        if (ch.nodeType === Node.TEXT_NODE) {
          continue;
        }
        if (ch.nodeType === Node.ELEMENT_NODE) {
          const tag = ch.tagName.toUpperCase();
          if (!allowedTags.includes(tag)) {
            // Заменяем элемент на его содержимое (удаляем сам тег)
            const inner = document.createDocumentFragment();
            while (ch.firstChild) inner.appendChild(ch.firstChild);
            node.replaceChild(inner, ch);
            // продолжим с теми узлами что вставили
            clean(node);
            continue;
          } else {
            // разрешённый тег — очистим атрибуты кроме href у <a>
            const attrs = Array.from(ch.attributes || []);
            for (const at of attrs) {
              if (ch.tagName.toUpperCase() === 'A') {
                if (at.name !== 'href' && at.name !== 'target' && at.name !== 'rel') {
                  ch.removeAttribute(at.name);
                }
              } else {
                ch.removeAttribute(at.name);
              }
            }
            // для ссылок — безопасный href (удаляем javascript:)
            if (ch.tagName.toUpperCase() === 'A') {
              const href = ch.getAttribute('href') || '';
              if (/^\s*javascript:/i.test(href)) {
                ch.removeAttribute('href');
              } else {
                // optional: привести к относительным/https — оставим как есть
                ch.setAttribute('rel', 'noopener noreferrer');
                ch.setAttribute('target', '_blank');
              }
            }
            // рекурсивно внутри
            clean(ch);
          }
        } else {
          // другие типы узлов — удаляем
          node.removeChild(ch);
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
    // modern: use execCommand as simple reliable way
    document.execCommand('insertHTML', false, html);
  }

  // --- Обработчики тулбара ---
  toolbar.addEventListener('click', function (e) {
    const btn = e.target.closest('button[data-cmd]');
    if (!btn) return;
    const cmd = btn.getAttribute('data-cmd');

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
      document.execCommand(cmd, false, null);
    }

    // обновить state кнопок
    updateToolbarState();
    editor.focus();
  });

  // Обновлять активные состояния кнопок при изменении выделения/курсор-перемещении
  editor.addEventListener('keyup', updateToolbarState);
  editor.addEventListener('mouseup', updateToolbarState);
  editor.addEventListener('focus', updateToolbarState);
  editor.addEventListener('blur', updateToolbarState);

  // --- Paste handling: sanitize, respect code-mode ---
  editor.addEventListener('paste', function (e) {
    e.preventDefault();
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
      const safeText = text
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/\r\n|\r|\n/g, '<br>');
      insertHTMLAtCursor(safeText);
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
    const val = textarea.value || '';
    if (val && val !== editor.innerHTML) {
      editor.innerHTML = sanitizeHTML(val);
    }
  });
  observer.observe(textarea, {attributes: true, attributeFilter: ['value'], subtree: false});

  // --- Клавиатурные шорткаты ---
  editor.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && !e.shiftKey) {
      if (e.key.toLowerCase() === 'b') {
        e.preventDefault();
        document.execCommand('bold', false, null);
        updateToolbarState();
      } else if (e.key.toLowerCase() === 'i') {
        e.preventDefault();
        document.execCommand('italic', false, null);
        updateToolbarState();
      }
    }
  });

  // Инициализация — очистка потенциально опасного HTML в textarea при старте
  textarea.value = sanitizeHTML(textarea.value || '');

})();

