{{-- resources/views/mail/bulk-upload-processed.blade.php --}}
@php
    $appName     = config('app.name', 'Aplicação');
    $userName    = $userName   ?? 'Gestor';
    $fileName    = $fileName   ?? 'colaboradores.csv';
    $created     = (int) ($created   ?? 0);
    $skipped     = (int) ($skipped   ?? 0);
    $total       = (int) ($total     ?? ($created + $skipped));
    $startedAt   = $startedAt ?? null;
    $finishedAt  = $finishedAt ?? null;
    $duration    = $duration  ?? null;
    $dashboardUrl = $dashboardUrl ?? config('app.url');
    $errors      = is_array($errors ?? null) ? $errors : [];
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>{{ $appName }} • Processamento concluído</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { margin:0; padding:0; background:#0b1220; color:#e5e7eb; -webkit-font-smoothing:antialiased; }
    table { border-spacing:0; border-collapse:collapse; }
    img { border:0; line-height:100%; outline:none; text-decoration:none; }
    a { color:#10b981; text-decoration:none; }
    .wrapper { width:100%; background:#0b1220; padding:24px; }
    .container { max-width:600px; margin:0 auto; background:#0f172a; border-radius:12px; overflow:hidden; border:1px solid #1f2937; }
    .header { padding:20px 24px; background:#0b1220; border-bottom:1px solid #1f2937; }
    .brand { font:600 16px/1.2 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif; color:#10b981; letter-spacing:.2px; }
    .content { padding:24px; font:400 14px/1.6 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif; color:#e5e7eb; }
    .h1 { margin:0 0 8px; font:700 20px/1.3 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif; color:#ffffff; }
    .muted { color:#94a3b8; }
    .stats { width:100%; margin:16px 0 8px; border:1px solid #1f2937; border-radius:10px; overflow:hidden; }
    .stats th, .stats td { padding:10px 12px; text-align:left; font-size:13px; }
    .stats thead { background:#0b1220; color:#9ca3af; }
    .stats tbody tr + tr td { border-top:1px solid #1f2937; }
    .ok { color:#10b981; font-weight:600; }
    .warn { color:#f59e0b; font-weight:600; }
    .total { color:#60a5fa; font-weight:600; }
    .btn { display:inline-block; margin:16px 0 0; background:#10b981; color:#052e2b; padding:10px 16px; border-radius:8px; font-weight:700; }
    .errors { width:100%; margin:12px 0 0; border:1px solid #1f2937; border-radius:10px; overflow:hidden; }
    .errors th, .errors td { padding:10px 12px; text-align:left; font-size:13px; }
    .errors thead { background:#0b1220; color:#fca5a5; }
    .errors tbody tr + tr td { border-top:1px solid #1f2937; }
    .footer { padding:18px 24px; text-align:center; color:#64748b; font:400 12px/1.5 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif; }
    @media (max-width: 640px) {
      .content, .header, .footer { padding:18px !important; }
    }
  </style>
</head>
<body>
  <div class="wrapper">
    <table class="container" role="presentation" width="100%" aria-hidden="true">
      <tr>
        <td class="header">
          <div class="brand">{{ $appName }}</div>
        </td>
      </tr>
      <tr>
        <td class="content">
          <h1 class="h1">Processamento concluído</h1>
          <p>Olá, <strong>{{ $userName }}</strong>! Finalizamos o processamento do arquivo <strong>{{ $fileName }}</strong>.</p>

          @if($startedAt || $finishedAt || $duration)
            <p class="muted" style="margin-top:4px;">
              @if($startedAt) Iniciado: <strong>{{ $startedAt }}</strong>@endif
              @if($startedAt && ($finishedAt || $duration)) • @endif
              @if($finishedAt) Concluído: <strong>{{ $finishedAt }}</strong>@endif
              @if($duration) • Duração: <strong>{{ $duration }}</strong>@endif
            </p>
          @endif

          <table class="stats" role="presentation">
            <thead>
              <tr>
                <th scope="col">Métrica</th>
                <th scope="col">Valor</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Registros criados</td>
                <td class="ok">{{ number_format($created) }}</td>
              </tr>
              <tr>
                <td>Registros ignorados</td>
                <td class="warn">{{ number_format($skipped) }}</td>
              </tr>
              <tr>
                <td>Total processado</td>
                <td class="total">{{ number_format($total) }}</td>
              </tr>
            </tbody>
          </table>

          @if($skipped > 0 && !empty($errors))
            <p class="muted" style="margin:10px 0 6px;">Amostra de linhas ignoradas (máx. 10):</p>
            <table class="errors" role="presentation">
              <thead>
                <tr>
                  <th scope="col">Linha</th>
                  <th scope="col">Motivo</th>
                </tr>
              </thead>
              <tbody>
              @foreach(array_slice($errors, 0, 10) as $err)
                <tr>
                  <td>#{{ $err['line'] ?? '?' }}</td>
                  <td>{{ $err['reason'] ?? 'Motivo não informado' }}</td>
                </tr>
              @endforeach
              </tbody>
            </table>
          @endif

          <a class="btn" href="{{ $dashboardUrl }}" target="_blank" rel="noopener">Ver colaboradores</a>

          <p class="muted" style="margin-top:14px;">Se você não reconhece esta operação, por favor responda a este e-mail.</p>
        </td>
      </tr>
      <tr>
        <td class="footer">
          © {{ date('Y') }} {{ $appName }}. Todos os direitos reservados.
        </td>
      </tr>
    </table>
  </div>
</body>
</html>
