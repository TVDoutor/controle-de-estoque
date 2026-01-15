<?php

declare(strict_types=1);

$errorTitle = $errorTitle ?? 'Algo deu errado';
$errorMessage = $errorMessage ?? 'No foi possvel concluir a operao.';
$helpText = $helpText ?? null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($errorTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap');
        :root {
            color-scheme: light dark;
        }
        body {
            margin: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #0f172a, #1e293b 60%, #111827);
            color: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
        }
        .card {
            background: rgba(15, 23, 42, 0.78);
            border: 1px solid rgba(148, 163, 184, 0.25);
            border-radius: 20px;
            max-width: 480px;
            width: 100%;
            padding: 32px;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.35);
            backdrop-filter: blur(12px);
        }
        h1 {
            font-size: 1.75rem;
            margin: 0 0 12px;
            font-weight: 600;
        }
        p {
            margin: 0 0 16px;
            line-height: 1.6;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 999px;
            border: 1px solid rgba(96, 165, 250, 0.4);
            background: rgba(59, 130, 246, 0.2);
            color: #bfdbfe;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.2s, transform 0.2s;
        }
        .btn:hover {
            background: rgba(59, 130, 246, 0.35);
            transform: translateY(-1px);
        }
        .muted {
            color: #94a3b8;
            font-size: 0.875rem;
        }
        @media (max-width: 480px) {
            .card {
                padding: 28px 22px;
            }
            h1 {
                font-size: 1.45rem;
            }
        }
    </style>
</head>
<body>
    <div class="card">
        <h1><?= htmlspecialchars($errorTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p><?= nl2br(htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8')); ?></p>
        <?php if ($helpText): ?>
            <p class="muted"><?= nl2br(htmlspecialchars($helpText, ENT_QUOTES, 'UTF-8')); ?></p>
        <?php endif; ?>
        <a class="btn" href="javascript:history.back();" onclick="window.location.reload();">
             Tentar novamente
        </a>
    </div>
</body>
</html>
