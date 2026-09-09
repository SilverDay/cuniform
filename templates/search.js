(function () {
  'use strict';

  var root = document.querySelector('[data-cuniform-search]');
  if (!root) {
    return;
  }

  var lang = root.getAttribute('data-lang');
  var indexUrl = root.getAttribute('data-index-url');
  var input = root.querySelector('input[type="search"]');
  var results = root.querySelector('.search-results');
  var noResults = root.querySelector('.search-no-results');
  var entries = null;

  function escapeHtml(value) {
    var div = document.createElement('div');
    div.textContent = value;
    return div.innerHTML;
  }

  function render(matches) {
    results.innerHTML = '';
    noResults.hidden = matches.length !== 0;

    matches.forEach(function (entry) {
      var li = document.createElement('li');
      var a = document.createElement('a');
      a.href = entry.path;
      a.innerHTML = escapeHtml(entry.title);
      li.appendChild(a);

      if (entry.summary) {
        var p = document.createElement('p');
        p.innerHTML = escapeHtml(entry.summary);
        li.appendChild(p);
      }

      results.appendChild(li);
    });
  }

  function search(query) {
    if (entries === null || query.trim() === '') {
      results.innerHTML = '';
      noResults.hidden = true;
      return;
    }

    var needle = query.trim().toLowerCase();
    var matches = entries.filter(function (entry) {
      if (entry.lang !== lang) {
        return false;
      }

      var haystack = [entry.title, entry.summary, entry.body_plain]
        .concat(entry.tags || [])
        .join(' ')
        .toLowerCase();

      return haystack.indexOf(needle) !== -1;
    });

    render(matches);
  }

  input.addEventListener('input', function () {
    search(input.value);
  });

  fetch(indexUrl, { credentials: 'same-origin' })
    .then(function (response) {
      return response.json();
    })
    .then(function (data) {
      entries = data;
      search(input.value);
    })
    .catch(function () {
      entries = [];
    });
})();
