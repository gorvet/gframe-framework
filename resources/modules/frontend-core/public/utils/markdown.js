// Basic markdown helpers for simple inline formatting.

function markdown2Html(content) {
  var input = String(content || '');
  return input
    .replace(/\n/g, '<br>')
    .replace(/\*(.*?)\*/g, '<strong>$1</strong>')
    .replace(/_(.*?)_/g, '<em>$1</em>')
    .replace(/~(.*?)~/g, '<del>$1</del>');
}

function html2Markdown(content) {
  var input = String(content || '');
  return input
    .replace(/<br\s*\/?>\s*\n?/g, '\n')
    .replace(/<strong>(.*?)<\/strong>/g, '*$1*')
    .replace(/<em>(.*?)<\/em>/g, '_$1_')
    .replace(/<del>(.*?)<\/del>/g, '~$1~');
}
