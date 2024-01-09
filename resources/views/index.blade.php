<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editable Texts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
  </head>
  <body>
  <main>

  <header class="p-3 text-bg-dark fixed-top">
    <div class="container">
      <div class="d-flex flex-wrap align-items-center justify-content-center justify-content-lg-start">
        <ul class="nav col-12 col-lg-auto me-lg-auto mb-2 justify-content-center mb-md-0">
          @foreach(config('translate.languages') as $l => $name)
          <li><a href="{{ route('translate.texts', ['lang' => $l]) }}" class="nav-link px-2 {!! $lang == $l ? 'text-secondary' : 'text-white' !!}">{{ $name }}</a></li>
          @endforeach
        </ul>

        <div class="text-end">
          <a href="/" class="btn btn-outline-light me-2">{{ trans('translate::messages.btn_close') }}</a>
        </div>
      </div>
    </div>
  </header>

  <div class="container" style="margin-top: 100px">
    <form method="get" action="{{ route('translate.texts') }}">
    <input type="hidden" name="lang" value="{{ $lang }}" />
    <table class="table table-striped">
    <thead>
        <tr>
            <th>{{ trans('translate::messages.code_label') }}</th>
            <th>{{ trans('translate::messages.text_label') }}</th>
        </tr>
        <tr>
            <th><input type="search" enterkeyhint="search" class="form-control" name="code" value="{{ $search['code']??'' }}" /></th>
            <th><input type="search" enterkeyhint="search" class="form-control" name="text" value="{{ $search['text']??'' }}" />
        </th>
        </tr>
    </thead>
    <tbody>
        @foreach($codes as $code)
        <tr>
            <td>
                <code>{{ $code }}</code>
            </td>
            <td>
                <span data-text="{{ $code }}">
                {{ strip_tags(T::trans($code)) }}
                </span>
            </td>
        </tr>
        @endforeach
    </tbody>
    </table>
    </form>

    </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    <script>
    (function () {
        const f = document.querySelector('form');
        f.querySelectorAll('input[type="search"]').forEach(el => {
            el.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    f.submit();
                }
            });
            el.addEventListener("search", () => {
                f.submit();
            });
        });
    }());
    </script>
    @includeWhen(session('editable_texts'), 'translate::editable_texts', ['translate_skip_banner' => true])
  </body>
</html>