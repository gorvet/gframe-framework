(function(window, document) {
  'use strict';
  function options() {
    var style = window.getComputedStyle(document.documentElement);
    var color = style.getPropertyValue('--bs-body-color').trim();
    var border = style.getPropertyValue('--bs-border-color').trim();
    return {
      color: color,
      font: {family: style.getPropertyValue('--bs-body-font-family').trim(), size: parseFloat(style.getPropertyValue('--bs-body-font-size')) || 14},
      scales: {x: {ticks: {color: color}, grid: {color: border}}, y: {ticks: {color: color}, grid: {color: border}}},
      plugins: {legend: {labels: {color: color}}, tooltip: {
        backgroundColor: style.getPropertyValue('--bs-body-bg').trim(),
        titleColor: color, bodyColor: color, borderColor: border, borderWidth: 1
      }}
    };
  }
  function apply(chart) {
    var theme = options();
    // La configuración original evita reutilizar los proxies resueltos por ChartJS.
    var settings = chart.config ? chart.config.options : chart.options;
    settings.color = theme.color;
    settings.font = Object.assign({}, settings.font, theme.font);
    Object.keys(settings.scales || {}).forEach(function(key) {
      var scale = settings.scales[key];
      scale.ticks = Object.assign({}, scale.ticks, {color: theme.color});
      scale.grid = Object.assign({}, scale.grid, {color: theme.scales.x.grid.color});
    });
    var plugins = settings.plugins || (settings.plugins = {});
    if (plugins.legend !== false) {
      plugins.legend = plugins.legend || {};
      plugins.legend.labels = Object.assign({}, plugins.legend.labels, {color: theme.color});
    }
    if (plugins.tooltip !== false) plugins.tooltip = Object.assign({}, plugins.tooltip, theme.plugins.tooltip);
    chart.update('none');
  }
  window.GFrameChartTheme = {
    options: options,
    apply: apply,
    watch: function(chart) {
      apply(chart);
      var observer = new MutationObserver(function() { if (chart.canvas) apply(chart); });
      observer.observe(document.documentElement, {attributes: true, attributeFilter: ['data-bs-theme', 'style', 'class']});
      return function() { observer.disconnect(); };
    }
  };
})(window, document);
