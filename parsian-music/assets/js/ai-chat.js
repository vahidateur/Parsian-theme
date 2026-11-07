/**
 * Parsian floating AI assistant.
 */
(function () {
  'use strict';

  function init() {
  var root = document.querySelector('[data-pm-ai]');
  if (!root || typeof window.pmAi === 'undefined') {
    return;
  }

  var fab = root.querySelector('.pm-ai-fab');
  var panel = root.querySelector('.pm-ai-panel');
  var close = root.querySelector('.pm-ai-close');
  var messages = root.querySelector('[data-pm-ai-messages]');
  var starterBox = root.querySelector('[data-pm-ai-starters]');
  var form = root.querySelector('[data-pm-ai-form]');
  var input = form ? form.querySelector('textarea') : null;
  var submit = form ? form.querySelector('button[type="submit"]') : null;
  var isOpen = false;
  var isBusy = false;

  function setOpen(open) {
    isOpen = open;
    root.classList.toggle('is-open', open);
    fab.setAttribute('aria-expanded', open ? 'true' : 'false');
    panel.setAttribute('aria-hidden', open ? 'false' : 'true');
    if (open && input) {
      window.setTimeout(function () {
        input.focus();
      }, 120);
    }
  }

  function scrollToBottom() {
    messages.scrollTop = messages.scrollHeight;
  }

  function addMessage(role, text) {
    var message = document.createElement('div');
    message.className = 'pm-ai-msg is-' + role;
    message.textContent = text;
    messages.appendChild(message);
    scrollToBottom();
    return message;
  }

  function addSources(sources, container) {
    if (!Array.isArray(sources) || !sources.length) {
      return;
    }

    var list = document.createElement('div');
    list.className = 'pm-ai-sources';
    sources.slice(0, 3).forEach(function (source) {
      if (!source || !source.url) {
        return;
      }
      var link = document.createElement('a');
      link.href = source.url;
      link.textContent = source.title || 'منبع';
      list.appendChild(link);
    });

    if (list.children.length) {
      container.appendChild(list);
      scrollToBottom();
    }
  }

  function addFeedback(container, question, answer) {
    if (!window.pmAi.feedbackEndpoint || !window.pmAi.feedbackNonce) {
      return;
    }

    var box = document.createElement('div');
    box.className = 'pm-ai-feedback';
    var label = document.createElement('span');
    label.textContent = 'این پاسخ مفید بود؟';
    box.appendChild(label);

    ['up', 'down'].forEach(function (rating) {
      var button = document.createElement('button');
      button.type = 'button';
      button.dataset.pmFeedback = rating;
      button.textContent = rating === 'up' ? '👍' : '👎';
      button.setAttribute('aria-label', rating === 'up' ? 'بله، مفید بود' : 'نه، مفید نبود');
      button.addEventListener('click', async function () {
        box.querySelectorAll('button').forEach(function (item) {
          item.disabled = true;
        });
        try {
          var response = await fetch(window.pmAi.feedbackEndpoint, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-PM-Nonce': window.pmAi.feedbackNonce
            },
            body: JSON.stringify({ question: question, answer: answer, rating: rating })
          });
          if (response.ok) {
            box.innerHTML = '<span class="pm-ai-thanks">ممنون از بازخورد شما</span>';
          } else {
            box.innerHTML = '<span class="pm-ai-thanks">ثبت بازخورد ممکن نشد</span>';
          }
        } catch (error) {
          box.innerHTML = '<span class="pm-ai-thanks">ثبت بازخورد ممکن نشد</span>';
        }
      });
      box.appendChild(button);
    });

    container.appendChild(box);
  }

  function renderStarters() {
    if (!starterBox || !Array.isArray(window.pmAi.starters) || !window.pmAi.starters.length) {
      return;
    }

    window.pmAi.starters.forEach(function (text) {
      if (!text) {
        return;
      }
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'pm-ai-starter';
      button.textContent = text;
      button.addEventListener('click', function () {
        input.value = text;
        resizeInput();
        input.focus();
      });
      starterBox.appendChild(button);
    });
  }

  function typingMessage() {
    var message = document.createElement('div');
    message.className = 'pm-ai-msg is-bot is-typing';
    message.innerHTML = '<span></span><span></span><span></span>';
    messages.appendChild(message);
    scrollToBottom();
    return message;
  }

  function resizeInput() {
    if (!input) {
      return;
    }
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 110) + 'px';
  }

  async function sendMessage(text) {
    if (isBusy || !text) {
      return;
    }

    isBusy = true;
    submit.disabled = true;
    addMessage('user', text);
    input.value = '';
    resizeInput();

    var typing = typingMessage();

    try {
      var response = await fetch(window.pmAi.endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-PM-Nonce': window.pmAi.nonce
        },
        body: JSON.stringify({ message: text })
      });

      var data = await response.json();
      typing.remove();

      if (!response.ok) {
        addMessage('error', data && data.message ? data.message : window.pmAi.error);
        return;
      }

      var reply = data && data.reply ? data.reply : window.pmAi.error;
      var message = addMessage('bot', reply);
      addSources(data ? data.sources : [], message);
      addFeedback(message, text, reply);
    } catch (error) {
      typing.remove();
      addMessage('error', window.pmAi.error);
    } finally {
      isBusy = false;
      submit.disabled = false;
      if (isOpen) {
        input.focus();
      }
    }
  }

  fab.addEventListener('click', function () {
    setOpen(!isOpen);
  });

  close.addEventListener('click', function () {
    setOpen(false);
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && isOpen) {
      setOpen(false);
    }
  });

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    var text = input.value.trim();
    if (text) {
      sendMessage(text);
    }
  });

  input.addEventListener('input', resizeInput);

  input.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    }
  });

  renderStarters();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
