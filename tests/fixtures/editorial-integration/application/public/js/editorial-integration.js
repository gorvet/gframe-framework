(function () {
  const form = document.getElementById('editorialForm');
  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    const operation = event.submitter.value;
    const snapshot = AdminRichTextEditor.getSaveSnapshot('article-content');
    const values = new URLSearchParams(new FormData(form));
    new FormData(document.getElementById('tokens')).forEach((value, key) => values.set(key, value));
    values.set('operation', operation); values.set('content', snapshot.content);
    if (operation === 'schedule') values.set('publish_at', new Date(form.publish_at.value).toISOString().slice(0, 19).replace('T', ' '));
    const response = await fetch(form.dataset.url, {method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: values});
    const result = await response.json();
    document.getElementById('editorialResult').textContent = result.status === 'success' ? form.dataset.saved : (result.message || form.dataset.failed);
    if (result.status === 'success' && result.data?.revision) {
      form.content_revision.value = result.data.revision;
      AdminRichTextEditor.markSaved(snapshot, result.data.revision);
    }
    form.dataset.result = JSON.stringify(result);
  });
})();
