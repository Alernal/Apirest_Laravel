<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>

    <!-- Bootstrap CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        body {
            background-color: #f8f9fa;
        }

        .verification-container {
            max-width: 500px;
            margin: 80px auto;
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            text-align: center;
        }

        .btn-primary {
            background-color: #007bff;
            border: none;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <h1 class="mb-4">{{ $title }}</h1>

        @if ($status === 'success')
            <div class="alert alert-success">{{ $message }}</div>
        @elseif ($status === 'error')
            <div class="alert alert-danger">{{ $message }}</div>
        @else
            <div class="alert alert-info">{{ $message }}</div>
        @endif

        <a href="https://nurae.alernal.com.co" class="btn btn-primary mt-4">Ir a la tienda</a>
    </div>
</body>
</html>
