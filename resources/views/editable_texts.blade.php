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
      el.innerHTML = '<i class="fa fa-pencil me-2 fs-4 fw-normal"></i>';
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
