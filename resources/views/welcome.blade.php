<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FF — Portal</title>
  <meta name="description" content="Portal informativo. Acceso al sistema.">
  <link rel="icon" href="{{ asset('none.ico') }}" sizes="any">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    :root{
      /* === PALETA CYAN === */
      --cyan:#00bcd4;
      --cyan-osc:#0097a7;
      --cyan-claro:#4dd0e1;

      --bg-dark: rgba(0,0,0,.35);
      --card-bg: rgba(255,255,255,.88);
      --text: #0f0f10;
    }

    html, body { height: 100%; }
    body {
      margin:0;
      color: var(--text);
      font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
    }

    /* HERO pantalla completa */
    .hero{
      position: relative;
      min-height: 100vh;
      display: grid;
      place-items: center;
      overflow: hidden;
      background:#000;
    }

    .hero::before{
      content:"";
      position:absolute;
      inset:0;
      background-image: url('{{ asset('img/fondo.png') }}'); /* IMAGEN DE FONDO */
      background-size: cover;
      background-position: center;
      filter: blur(10px) saturate(1.1);
      transform: scale(1.05);
    }

    .hero::after{
      content:"";
      position:absolute;
      inset:0;
      background:
        radial-gradient(1200px 600px at 30% 10%,
          rgba(77,208,225,.25),
          rgba(0,188,212,.35) 55%,
          rgba(0,151,167,.55) 100%
        ),
        linear-gradient(180deg,
          rgba(0,0,0,.10),
          rgba(0,0,0,.25)
        );
    }

    /* Card central */
    .welcome-card{
      position: relative;
      z-index: 1;
      max-width: 720px;
      width: 92%;
      background: var(--card-bg);
      backdrop-filter: blur(6px);
      border-radius: 18px;
      box-shadow: 0 20px 60px rgba(0,0,0,.25);
      padding: clamp(1.5rem, 2.5vw, 2.2rem);
    }

    .brand{
      font-weight: 900;
      letter-spacing: .5px;
      text-transform: uppercase;
      color: var(--cyan);
    }

    .muted{
      color: #4b4b4b;
      font-size: .95rem;
    }

    /* Topbar */
    .topbar{
      position: fixed;
      inset: 0 0 auto 0;
      height: 56px;
      display:flex;
      align-items:center;
      z-index: 2;
      background: rgba(255,255,255,.65);
      backdrop-filter: blur(8px);
      border-bottom: 1px solid rgba(0,0,0,.06);
    }

    /* Botones CYAN */
    .btn-cyan{
      background: var(--cyan);
      color:#fff;
      border:none;
      font-weight:700;
    }
    .btn-cyan:hover{
      background: var(--cyan-osc);
      color:#fff;
    }
  </style>
</head>

<body>

  <!-- Barra superior -->
  <div class="topbar">
    <div class="container d-flex justify-content-between align-items-center">
      <div class="brand">FF</div>
      <div>
        <a class="btn btn-cyan btn-sm" href="{{ route('login') }}">
          Iniciar sesión
        </a>
      </div>
    </div>
  </div>

  <!-- Sección principal -->
  <main class="hero">
    <section class="welcome-card text-center">
      <h1 class="h3 mb-2 fw-bold">Bienvenido</h1>
      <p class="muted mb-4">
        Portal informativo de acceso al sistema.
      </p>
      <a href="{{ route('login') }}" class="btn btn-cyan btn-lg px-4">
        Entrar al sistema
      </a>
    </section>
  </main>

  <footer class="text-center py-4" style="position:relative; z-index:1;">
    <small class="text-muted">© {{ date('Y') }}. Uso interno.</small>
  </footer>

</body>
</html>
