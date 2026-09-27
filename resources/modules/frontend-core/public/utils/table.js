// Dynamic table plugin (search + sort).

$.fn.gfTable = function(options) {
  var settings = $.extend({
    searchSelector: '.gf-search',
    containerSelector: 'div',
    debounceDelay: 300,
    animateSorting: true
  }, options);

  return this.each(function() {
    var $table = $(this);
    if ($table.data('gfTable-initialized')) return;
    $table.data('gfTable-initialized', true);

    var $tbody = $table.find('tbody');
    var $headers = $table.find('th.sortable');
    var $search = $table.closest(settings.containerSelector).find(settings.searchSelector).first();
    var $info = $('<div class="gf-info small text-muted mt-2"></div>').insertAfter($table);
    var currentSort = { column: null, direction: 'asc' };
    var searchTimeout;

    var getCellValue = function(row, columnIndex, type) {
      var cell = row.cells[columnIndex];
      var $cell = $(cell);
      var $select = $cell.find('select');
      var v = '';

      if ($select.length) {
        v = $select.find('option:selected').text().trim();
      } else {
        v = $cell.text().trim();
      }

      if (type === 'number') return parseFloat(v.replace(/[^\d.-]/g, '')) || 0;
      if (type === 'date') return new Date(v).getTime() || 0;
      return v;
    };

    var comparer = function(columnIndex, type, asc) {
      return function(a, b) {
        var v1 = getCellValue(a, columnIndex, type);
        var v2 = getCellValue(b, columnIndex, type);
        if (type === 'number') return asc ? v1 - v2 : v2 - v1;
        var str1 = String(v1).toLowerCase();
        var str2 = String(v2).toLowerCase();
        if (str1 < str2) return asc ? -1 : 1;
        if (str1 > str2) return asc ? 1 : -1;
        return 0;
      };
    };

    var updateInfo = function() {
      if (!$info.length) return;
      var total = $tbody.find('tr').length;
      var visibles = $tbody.find('tr:visible').length;
      $info.text('Mostrando ' + visibles + ' de ' + total + ' registros');
    };

    var sortTable = function(headerIdx, type) {
      if (settings.animateSorting) $table.addClass('gf-table-sorting');

      setTimeout(function() {
        var $th = $headers.eq(headerIdx);
        var cellIndex = $th.index();
        var same = currentSort.column === headerIdx;
        var dir = same && currentSort.direction === 'asc' ? 'desc' : 'asc';
        currentSort = { column: headerIdx, direction: dir };

        $headers.removeClass('sorted-asc sorted-desc').attr('aria-sort', 'none');
        $th.addClass(dir === 'asc' ? 'sorted-asc' : 'sorted-desc')
          .attr('aria-sort', dir === 'asc' ? 'ascending' : 'descending');

        var $rows = $tbody.find('tr:visible').get();
        $rows.sort(comparer(cellIndex, type, dir === 'asc'));
        $.each($rows, function(_, r) { $tbody.append(r); });

        if (settings.animateSorting) {
          setTimeout(function() { $table.removeClass('gf-table-sorting'); }, 100);
        }

        updateInfo();
        $table.trigger('gfTable.sorted', [headerIdx, dir, type]);
      }, 10);
    };

    var filterTable = function(q) {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(function() {
        q = String(q || '').toLowerCase().trim();
        $tbody.find('tr').each(function() {
          var text = $(this).text().toLowerCase();
          $(this).toggle(text.indexOf(q) > -1);
        });

        if (currentSort.column !== null) {
          var type = $headers.eq(currentSort.column).data('type') || 'text';
          sortTable(currentSort.column, type);
        } else {
          updateInfo();
        }

        $table.trigger('gfTable.filtered', [q]);
      }, settings.debounceDelay);
    };

    $headers.each(function(headerIndex) {
      var $header = $(this);
      var type = $header.data('type') || 'text';
      $header.attr({ tabindex: 0, 'aria-sort': 'none', role: 'button' })
        .on('click', function() { sortTable(headerIndex, type); })
        .on('keydown', function(e) {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            sortTable(headerIndex, type);
          }
        });
    });

    if ($search.length) {
      $search.on('input', function() {
        filterTable($(this).val());
      });
    }

    $table.data('gfTable', {
      sort: function(columnIndex) {
        var type = $headers.eq(columnIndex).data('type') || 'text';
        sortTable(columnIndex, type);
      },
      filter: function(query) {
        if ($search.length) {
          $search.val(query).trigger('input');
        } else {
          filterTable(query);
        }
      },
      reset: function() {
        if ($search.length) $search.val('').trigger('input');
        $headers.removeClass('sorted-asc sorted-desc').attr('aria-sort', 'none');
        currentSort = { column: null, direction: 'asc' };
        updateInfo();
        $table.trigger('gfTable.reset');
      }
    });

    updateInfo();
  });
};

$(function() {
  $('.gf-table').gfTable();

  if (typeof MutationObserver !== 'undefined') {
    var observer = new MutationObserver(function(mutations) {
      mutations.forEach(function(mutation) {
        $(mutation.addedNodes).find('.gf-table').each(function() {
          if (!$(this).data('gfTable-initialized')) {
            $(this).gfTable();
          }
        });
      });
    });

    observer.observe(document.body, { childList: true, subtree: true });
  }
});
