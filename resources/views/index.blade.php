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


  <div class="container">

    <ul class="nav nav-tabs">
        @php $l = config('translate.default_language') @endphp
        <li class="nav-item">
            <a class="nav-link {!! $lang == $l ? 'active' : '' !!}" href="{{ route('translate.texts', ['lang' => $l]) }}">{{ $l }}</a>
        </li>
        @foreach(config('translate.translated_languages') as $l)
        <li class="nav-item">
            <a class="nav-link {!! $lang == $l ? 'active' : '' !!}" href="{{ route('translate.texts', ['lang' => $l]) }}">{{ $l }}</a>
        </li>
        @endforeach

    </ul>

    <table class="table table-striped">
    <thead>
        <tr>
            <th>ID</th>
            <th>Text</th>
        </tr>
    </thead>
    <tbody>
        @foreach($codes as $code)
        <tr>
            <td>
                <code>{{ $code }}</code>
            </td>
            <td>
                @t($code)
            </td>
        </tr>
        @endforeach
    </tbody>
    </table>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>

    @includeWhen(session('editable_texts'), 'translate::editable_texts')
  </body>
</html>