@include('errors.layout', [
    'code' => 422,
    'title' => 'We couldn\'t process that',
    'message' => $exception->getMessage() ?: 'Something in the request wasn\'t valid.',
    'retry' => false,
    'showReference' => true,
])
