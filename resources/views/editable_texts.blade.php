@php

$lang = \App::getLocale();

@endphp

<link href="https://cdn.jsdelivr.net/npm/ace-builds@1.18.0/css/ace.min.css" rel="stylesheet">

@if(empty($translate_skip_banner))
<div class="p-5 bg-light text-center" style="position:fixed;bottom:0;right:0;z-index:2000">
<p>{{ trans('translate::messages.intro') }} <a href="{{ route('translate.texts') }}">{{ trans('translate::messages.view_all') }}</a></p>
<p><a class="btn btn-sm btn-danger" href="/?editable_texts=end">{{ trans('translate::messages.btn_disable') }}</a></p>
</div>
@endif

<div class="modal" tabindex="-1" id="textsModal">
    <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ trans('translate::messages.code_label') }}: <code></code></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <div class="text-editor" id="text-editor" style="min-height:250px"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ trans('translate::messages.btn_close') }}</button>
        <button type="button" class="btn btn-primary">{{ trans('translate::messages.btn_save') }}</button>
        <input type="hidden" name="id" value="" />
        <input type="hidden" name="lang" value="{!! $lang !!}" />
      </div>
    </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/ace-builds@1.18.0/src-min-noconflict/ace.min.js"></script>
<script>

(function() {

const textModal = document.getElementById('textsModal');
const bsModal = new bootstrap.Modal(textModal);
const url = "{{ route('translate.text', ['id' => '__id__', 'lang' => '__lang__']) }}";
const err_not_found = "{{ trans('translate::messages.err_not_found') }}";

textModal.querySelector('button.btn-primary').addEventListener('click', e => {
    saveText();
});

const editor = ace.edit('text-editor');
editor.setTheme('ace/theme/solarized_light');
editor.session.setMode("ace/mode/html");
editor.setOptions({
  fontSize: 14,
  wrap: 'free',
});
const session = editor.getSession();
session.on("changeAnnotation", function() {
  var annotations = session.getAnnotations()||[], i = len = annotations.length;
  while (i--) {
    if (/doctype first\. Expected/.test(annotations[i].text)) {
      annotations.splice(i, 1);
    }
  }
  if (len>annotations.length) {
    session.setAnnotations(annotations);
  }
});

const pencilSVG = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="24" height="24"> \
  <path d="M11 4H7.2C6.0799 4 5.51984 4 5.09202 4.21799C4.71569 4.40974 4.40973 4.7157 4.21799 5.09202C4 5.51985 4 6.0799 4 7.2V16.8C4 17.9201 4 18.4802 4.21799 18.908C4.40973 19.2843 4.71569 19.5903 5.09202 19.782C5.51984 20 6.0799 20 7.2 20H16.8C17.9201 20 18.4802 20 18.908 19.782C19.2843 19.5903 19.5903 19.2843 19.782 18.908C20 18.4802 20 17.9201 20 16.8V12.5M15.5 5.5L18.3284 8.32843M10.7627 10.2373L17.411 3.58902C18.192 2.80797 19.4584 2.80797 20.2394 3.58902C21.0205 4.37007 21.0205 5.6364 20.2394 6.41745L13.3774 13.2794C12.6158 14.0411 12.235 14.4219 11.8012 14.7247C11.4162 14.9936 11.0009 15.2162 10.564 15.3882C10.0717 15.582 9.54378 15.6885 8.48793 15.9016L8 16L8.04745 15.6678C8.21536 14.4925 8.29932 13.9048 8.49029 13.3561C8.65975 12.8692 8.89125 12.4063 9.17906 11.9786C9.50341 11.4966 9.92319 11.0768 10.7627 10.2373Z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/> \
</svg>';

async function editText(id) {
    const lang = textModal.querySelector('input[name="lang"]').value;
    const response = await fetch(url.replace('__id__', id).replace('__lang__', lang));
    if (!response.ok) { alert("HTTP Error: " + response.status); return; }
    const data = await response.json();
    if (!data.success) { alert(`Error: ${err_not_found}`); return; }
    // textModal.querySelector('#text-editor').textContent = data.content;
    editor.resize();
    editor.setValue(data.content);
    textModal.querySelector('.modal-title code').textContent = id;
    textModal.querySelector('input[name="id"]').value = id;
    bsModal.show();
}

async function saveText() {
    const lang = textModal.querySelector('input[name="lang"]').value;
    const id = textModal.querySelector('input[name="id"]').value;
    const content = editor.getValue(); // textModal.querySelector('#text-editor').textContent;

    const response = await fetch(url.replace('__id__', id).replace('__lang__', lang), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json;charset=utf-8'
        },
        body: JSON.stringify({content, _token: "{{ csrf_token() }}"}),
    });
    if (!response.ok) { alert(`HTTP Error: ${response.status}`); return; }
    const data = await response.json();
    if (!data.success) { alert(`Error: ${data.msg}`); return; }
    bsModal.hide();
    document.location.reload();
}

document.querySelectorAll('span[data-text]').forEach(el => {
    const code = el.getAttribute('data-text');
    if (!el.textContent) {
      el.innerHTML = `<i class="me-2 fw-normal text-light">${pencilSVG}</i>`;
    }
    el.style.cursor = 'pointer';
    el.setAttribute('title', "{{ trans('translate::messages.btn_edit') }}");
    el.addEventListener('click', e => {
      e.preventDefault();
      e.stopPropagation();
      editText(code);
    });
});

})();

</script>
