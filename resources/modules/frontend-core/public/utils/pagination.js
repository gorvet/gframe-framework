// Pagination helpers used by list modules.

function buildPaginationItems(total_pages, page, visible_count) {
  if (total_pages <= 0) {
    return [];
  }

  visible_count = Math.max(1, visible_count || 5);
  page = Math.max(1, Math.min(page, total_pages));

  if (total_pages <= visible_count) {
    var allPages = [];
    for (var idx = 1; idx <= total_pages; idx++) {
      allPages.push(idx);
    }
    return allPages;
  }

  var half = Math.floor(visible_count / 2);
  var start = page - half;
  var end = page + half;

  if (start < 1) {
    end += (1 - start);
    start = 1;
  }

  if (end > total_pages) {
    start -= (end - total_pages);
    end = total_pages;
  }

  if (start < 1) {
    start = 1;
  }

  while ((end - start + 1) < visible_count && end < total_pages) {
    end += 1;
  }

  while ((end - start + 1) < visible_count && start > 1) {
    start -= 1;
  }

  var items = [];

  if (start > 1) {
    items.push(1);
    if (start > 2) {
      items.push('...');
    }
  }

  for (var i = start; i <= end; i++) {
    items.push(i);
  }

  if (end < total_pages) {
    if (end < (total_pages - 1)) {
      items.push('...');
    }
    items.push(total_pages);
  }

  return items;
}

function creaPaginacion(total_pages, page) {
  total_pages = parseInt(total_pages, 10) || 0;
  page = parseInt(page, 10) || 1;

  if (total_pages < 1) {
    return;
  }

  page = Math.max(1, Math.min(page, total_pages));
  var pageItems = buildPaginationItems(total_pages, page, 5);

  var upbody = $('#all_items_pagination');
  upbody.empty();
  var li = '';
  var item = '';

  if (page === 1) {
    li = $('<li class="page-item disabled">');
    item = '<span class="page-link">Anterior</span>';
  } else {
    li = $('<li class="page-item ">');
    item = '<a class="page-link prev" href="#">Anterior</a>';
  }
  li.append(item);
  upbody.append(li);

  for (var p = 0; p < pageItems.length; p++) {
    var value = pageItems[p];

    if (value === '...') {
      li = $('<li class="page-item disabled" aria-disabled="true">');
      item = '<span class="page-link">...</span>';
      li.append(item);
      upbody.append(li);
      continue;
    }

    if (page === value) {
      li = $('<li class="page-item active" aria-current="page">');
      item = '<span class="page-link">' + value + '</span>';
    } else {
      li = $('<li class="page-item">');
      item = '<a class="page-link linkeable" href="#">' + value + '</a>';
    }
    li.append(item);
    upbody.append(li);
  }

  if (page === total_pages) {
    li = $('<li class="page-item disabled">');
    item = '<span class="page-link">Siguiente</span>';
  } else {
    li = $('<li class="page-item ">');
    item = '<a class="page-link next" href="#">Siguiente</a>';
  }
  li.append(item);
  upbody.append(li);
}

window.fetchDataForPage = window.fetchDataForPage || null;

$(document).on('click', '.linkeable', function(event) {
  event.preventDefault();
  var numPage = $(this).text();
  if (!isNaN(numPage) && typeof window.fetchDataForPage === 'function') {
    window.fetchDataForPage(parseInt(numPage, 10));
  }
});

$(document).on('click', '.next', function(event) {
  event.preventDefault();
  var numPage = parseInt($('#all_items_pagination li.active span.page-link').text(), 10);
  numPage = numPage + 1;
  if (typeof window.fetchDataForPage === 'function') {
    window.fetchDataForPage(numPage);
  }
});

$(document).on('click', '.prev', function(event) {
  event.preventDefault();
  var numPage = parseInt($('#all_items_pagination li.active span.page-link').text(), 10);
  numPage = numPage - 1;
  if (typeof window.fetchDataForPage === 'function') {
    window.fetchDataForPage(numPage);
  }
});

function getDatasByPage(numPage, callback) {
  callback(numPage);
}
