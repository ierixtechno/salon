@include('errors.layout', [
    'code' => 404,
    'title' => 'Page not found',
    'message' => 'We couldn\'t find the page you were looking for. It may have moved, or the link may be wrong.',
    'retry' => false,
    'showReference' => false,
])
