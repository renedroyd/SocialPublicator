<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="VIRALYX centraliza, programa y potencia la comunicación digital de tu negocio para convertir visibilidad en oportunidades de venta.">
    <meta name="theme-color" content="#060816">
    <title>VIRALYX — Convierte tu presencia digital en crecimiento</title>
    <link rel="preconnect" href="https://images.unsplash.com">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --ink: #070a17;
            --ink-soft: #0c1022;
            --card: rgba(15, 20, 42, .72);
            --line: rgba(255,255,255,.10);
            --text: #f7f8ff;
            --muted: #aab2cc;
            --cyan: #08d9f5;
            --blue: #155bff;
            --violet: #6d35f5;
            --pink: #b33ff4;
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            background: var(--ink);
            color: var(--text);
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
            overflow-x: hidden;
        }
        a { color: inherit; text-decoration: none; }
        .vx-shell { background: var(--ink); }
        .vx-container { width: min(1180px, calc(100% - 40px)); margin: 0 auto; }
        .vx-nav {
            position: fixed; inset: 0 0 auto; z-index: 50;
            background: rgba(6,8,22,.68);
            border-bottom: 1px solid rgba(255,255,255,.07);
            backdrop-filter: blur(18px);
        }
        .vx-nav-inner { height: 76px; display:flex; align-items:center; justify-content:space-between; gap:24px; }
        .vx-brand { display:flex; align-items:center; gap:10px; font-weight:900; letter-spacing:.14em; }
        .vx-brand img { width:38px; height:38px; object-fit:contain; }
        .vx-brand span { font-size:17px; background:linear-gradient(100deg,var(--cyan),#6550f5,var(--pink)); -webkit-background-clip:text; background-clip:text; color:transparent; }
        .vx-nav-links { display:flex; gap:28px; color:#cbd1e5; font-size:14px; }
        .vx-nav-links a:hover { color:#fff; }
        .vx-btn {
            display:inline-flex; align-items:center; justify-content:center; gap:9px;
            border:1px solid transparent; border-radius:12px; padding:13px 20px;
            font-weight:800; font-size:14px; transition:transform .25s ease, box-shadow .25s ease, border-color .25s ease;
        }
        .vx-btn:hover { transform:translateY(-2px); }
        .vx-btn-primary {
            background:linear-gradient(110deg,var(--cyan),#3977ff 48%,var(--violet));
            box-shadow:0 12px 34px rgba(21,91,255,.25);
        }
        .vx-btn-ghost { border-color:rgba(255,255,255,.14); background:rgba(255,255,255,.04); }
        .vx-hero {
            min-height: 100vh; min-height: 100svh; position:relative; display:flex; align-items:center;
            background:
                linear-gradient(90deg, rgba(5,7,20,.97) 0%, rgba(5,7,20,.76) 46%, rgba(5,7,20,.30) 100%),
                linear-gradient(180deg, rgba(5,7,20,.25), var(--ink) 95%),
                url('https://images.unsplash.com/photo-1550751827-4bd374c3f58b?auto=format&fit=crop&w=2200&q=85') center/cover fixed;
            overflow:hidden;
        }
        .vx-hero::before {
            content:""; position:absolute; width:620px; height:620px; right:-180px; top:12%;
            background:radial-gradient(circle, rgba(8,217,245,.25), transparent 68%);
            filter:blur(20px); pointer-events:none;
        }
        .vx-hero-grid {
            position:absolute; inset:0; opacity:.13; pointer-events:none;
            background-image:linear-gradient(rgba(255,255,255,.16) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.16) 1px,transparent 1px);
            background-size:70px 70px; mask-image:linear-gradient(to bottom,black,transparent 88%);
        }
        .vx-hero-content { position:relative; z-index:2; padding:145px 0 100px; max-width:760px; }
        .vx-eyebrow {
            display:inline-flex; align-items:center; gap:8px; padding:7px 12px; border:1px solid rgba(8,217,245,.22);
            background:rgba(8,217,245,.07); color:#8cefff; border-radius:999px; font-size:12px; font-weight:800; letter-spacing:.12em; text-transform:uppercase;
        }
        .vx-eyebrow::before { content:""; width:7px; height:7px; border-radius:50%; background:var(--cyan); box-shadow:0 0 18px var(--cyan); }
        .vx-hero h1 { margin:22px 0 20px; font-size:clamp(44px,7vw,82px); line-height:.98; letter-spacing:-.055em; max-width:850px; }
        .vx-gradient { background:linear-gradient(100deg,var(--cyan),#5e8cff 46%,#c45af6); -webkit-background-clip:text; background-clip:text; color:transparent; }
        .vx-hero p { max-width:660px; color:#c1c8dc; font-size:clamp(18px,2vw,21px); line-height:1.65; margin:0 0 30px; }
        .vx-hero-actions { display:flex; flex-wrap:wrap; gap:12px; }
        .vx-trust { display:flex; flex-wrap:wrap; gap:22px; margin-top:32px; color:#929bb8; font-size:13px; }
        .vx-trust span { display:flex; align-items:center; gap:7px; }
        .vx-trust b { color:#e9ecf7; }

        .vx-section { padding:110px 0; position:relative; }
        .vx-section-dark { background:var(--ink); }
        .vx-section-soft { background:linear-gradient(180deg,#090d1d,#0c1124); }
        .vx-kicker { color:#8cefff; font-size:12px; font-weight:900; text-transform:uppercase; letter-spacing:.16em; }
        .vx-section-head { max-width:760px; margin-bottom:52px; }
        .vx-section-head h2 { font-size:clamp(34px,5vw,58px); line-height:1.04; letter-spacing:-.045em; margin:12px 0 18px; }
        .vx-section-head p { color:var(--muted); font-size:18px; line-height:1.7; margin:0; }
        .vx-grid-3 { display:grid; grid-template-columns:repeat(3,1fr); gap:18px; }
        .vx-card {
            padding:28px; border:1px solid var(--line); border-radius:22px;
            background:linear-gradient(145deg,rgba(255,255,255,.075),rgba(255,255,255,.025));
            box-shadow:0 24px 70px rgba(0,0,0,.18);
        }
        .vx-icon {
            width:48px; height:48px; display:grid; place-items:center; border-radius:14px;
            background:linear-gradient(135deg,rgba(8,217,245,.15),rgba(109,53,245,.22));
            border:1px solid rgba(255,255,255,.08); font-size:22px; margin-bottom:22px;
        }
        .vx-card h3 { font-size:20px; margin:0 0 10px; }
        .vx-card p { color:#9fa8c2; line-height:1.7; margin:0; }
        .vx-card strong { color:#fff; }

        .vx-parallax {
            min-height:560px; display:flex; align-items:center; position:relative; overflow:hidden;
            background-position:center; background-size:cover; background-attachment:fixed;
        }
        .vx-parallax::before { content:""; position:absolute; inset:0; background:linear-gradient(90deg,rgba(5,7,20,.94),rgba(5,7,20,.62),rgba(5,7,20,.36)); }
        .vx-parallax-1 { background-image:url('https://images.unsplash.com/photo-1516321318423-f06f85e504b?auto=format&fit=crop&w=2200&q=85'); }
        .vx-parallax-2 { background-image:url('https://images.unsplash.com/photo-1556761175-b413da4baf72?auto=format&fit=crop&w=2200&q=85'); }
        .vx-parallax-3 { background-image:url('https://images.unsplash.com/photo-1551434678-e076c223a692?auto=format&fit=crop&w=2200&q=85'); }
        .vx-parallax-content { position:relative; z-index:2; max-width:720px; }
        .vx-parallax h2 { font-size:clamp(36px,5vw,62px); line-height:1.02; letter-spacing:-.045em; margin:14px 0 18px; }
        .vx-parallax p { color:#d1d6e5; font-size:18px; line-height:1.75; margin:0; }

        .vx-flow { display:grid; grid-template-columns:repeat(4,1fr); gap:0; position:relative; }
        .vx-flow::before { content:""; position:absolute; top:28px; left:11%; right:11%; height:1px; background:linear-gradient(90deg,transparent,rgba(8,217,245,.35),rgba(109,53,245,.5),transparent); }
        .vx-step { position:relative; padding:0 18px; text-align:center; }
        .vx-step-num { width:56px; height:56px; margin:0 auto 22px; display:grid; place-items:center; border-radius:50%; background:#0c1228; border:1px solid rgba(8,217,245,.35); box-shadow:0 0 30px rgba(8,217,245,.10); font-weight:900; color:#8cefff; position:relative; z-index:1; }
        .vx-step h3 { margin:0 0 9px; font-size:18px; }
        .vx-step p { margin:0; color:#949db7; line-height:1.6; font-size:14px; }

        .vx-dashboard {
            border:1px solid rgba(255,255,255,.10); border-radius:28px; overflow:hidden;
            background:linear-gradient(145deg,#11172d,#090d1c); box-shadow:0 35px 100px rgba(0,0,0,.35);
        }
        .vx-windowbar { display:flex; align-items:center; gap:7px; padding:14px 18px; border-bottom:1px solid var(--line); }
        .vx-dot { width:8px; height:8px; border-radius:50%; background:#303950; }
        .vx-dashboard-body { display:grid; grid-template-columns:220px 1fr; min-height:360px; }
        .vx-sidebar { padding:24px 16px; border-right:1px solid var(--line); }
        .vx-side-item { padding:11px 13px; border-radius:10px; color:#7f89a5; font-size:13px; margin-bottom:5px; }
        .vx-side-item.active { color:#fff; background:linear-gradient(90deg,rgba(8,217,245,.12),rgba(109,53,245,.12)); }
        .vx-dash-main { padding:28px; }
        .vx-dash-title { display:flex; justify-content:space-between; gap:15px; margin-bottom:25px; }
        .vx-dash-title h3 { margin:0; font-size:20px; }
        .vx-pill { border:1px solid rgba(8,217,245,.2); background:rgba(8,217,245,.06); color:#8cefff; border-radius:999px; padding:7px 10px; font-size:11px; }
        .vx-mini-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; }
        .vx-mini { padding:18px; border:1px solid var(--line); border-radius:16px; background:rgba(255,255,255,.025); }
        .vx-mini small { color:#7d87a3; }
        .vx-mini strong { display:block; font-size:26px; margin-top:8px; }
        .vx-chart { margin-top:15px; height:145px; border-radius:16px; border:1px solid var(--line); position:relative; overflow:hidden; background:linear-gradient(180deg,rgba(8,217,245,.04),transparent); }
        .vx-chart::before { content:""; position:absolute; inset:15px 20px; background:linear-gradient(160deg,transparent 30%,rgba(8,217,245,.55) 31%,transparent 32%,transparent 52%,rgba(109,53,245,.7) 53%,transparent 54%,transparent 72%,rgba(179,63,244,.55) 73%,transparent 74%); }
        .vx-chart::after { content:""; position:absolute; left:20px; right:20px; bottom:27px; height:1px; background:rgba(255,255,255,.08); box-shadow:0 -38px rgba(255,255,255,.06),0 -76px rgba(255,255,255,.06); }

        .vx-stats { display:grid; grid-template-columns:repeat(4,1fr); border-top:1px solid var(--line); border-bottom:1px solid var(--line); }
        .vx-stat { padding:38px 20px; text-align:center; border-right:1px solid var(--line); }
        .vx-stat:last-child { border-right:0; }
        .vx-stat strong { display:block; font-size:clamp(30px,4vw,46px); letter-spacing:-.04em; }
        .vx-stat span { display:block; color:#8e98b4; margin-top:7px; font-size:13px; }

        .vx-cta {
            padding:120px 0; text-align:center; position:relative; overflow:hidden;
            background:radial-gradient(circle at 50% 35%,rgba(21,91,255,.22),transparent 35%),linear-gradient(180deg,#080b18,#060816);
        }
        .vx-cta::before { content:""; position:absolute; inset:0; opacity:.12; background-image:radial-gradient(circle at 20% 30%,#08d9f5 1px,transparent 1px),radial-gradient(circle at 80% 70%,#b33ff4 1px,transparent 1px); background-size:80px 80px,120px 120px; }
        .vx-cta-inner { position:relative; z-index:1; max-width:800px; margin:auto; }
        .vx-cta h2 { font-size:clamp(38px,6vw,68px); line-height:1; letter-spacing:-.05em; margin:14px 0 20px; }
        .vx-cta p { color:#aeb6cb; font-size:18px; line-height:1.7; margin:0 auto 30px; max-width:650px; }
        .vx-footer { border-top:1px solid var(--line); padding:28px 0; color:#707991; font-size:13px; }
        .vx-footer-inner { display:flex; justify-content:space-between; align-items:center; gap:20px; }
        .vx-footer-brand { color:#dfe4f2; font-weight:800; letter-spacing:.1em; }

        @media (max-width: 900px) {
            .vx-nav-links { display:none; }
            .vx-grid-3, .vx-flow { grid-template-columns:1fr 1fr; gap:18px; }
            .vx-flow::before { display:none; }
            .vx-stats { grid-template-columns:1fr 1fr; }
            .vx-stat:nth-child(2) { border-right:0; }
            .vx-stat:nth-child(-n+2) { border-bottom:1px solid var(--line); }
            .vx-dashboard-body { grid-template-columns:1fr; }
            .vx-sidebar { display:none; }
        }
        @media (max-width: 640px) {
            .vx-container { width:min(100% - 28px, 1180px); }
            .vx-nav-inner { height:66px; }
            .vx-nav .vx-btn { padding:10px 13px; font-size:12px; }
            .vx-brand img { width:32px; height:32px; }
            .vx-brand span { font-size:15px; }
            .vx-hero { background-attachment:scroll; }
            .vx-hero-content { padding:125px 0 80px; }
            .vx-hero h1 { font-size:clamp(40px,12vw,58px); }
            .vx-hero p { font-size:17px; }
            .vx-section { padding:78px 0; }
            .vx-grid-3, .vx-flow, .vx-mini-grid { grid-template-columns:1fr; }
            .vx-parallax { min-height:500px; background-attachment:scroll; }
            .vx-parallax::before { background:linear-gradient(90deg,rgba(5,7,20,.93),rgba(5,7,20,.68)); }
            .vx-stats { grid-template-columns:1fr 1fr; }
            .vx-stat { padding:27px 10px; }
            .vx-stat strong { font-size:29px; }
            .vx-dash-main { padding:18px; }
            .vx-footer-inner { flex-direction:column; align-items:flex-start; }
        }
        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior:auto; }
            *, *::before, *::after { transition:none !important; }
            .vx-parallax, .vx-hero { background-attachment:scroll; }
        }
    </style>
</head>
<body>
<div class="vx-shell">
    <header class="vx-nav">
        <div class="vx-container vx-nav-inner">
            <a class="vx-brand" href="#inicio" aria-label="VIRALYX inicio">
                <img src="{{ asset('images/viralyx-isotipo.svg') }}" alt="">
                <span>VIRALYX</span>
            </a>
            <nav class="vx-nav-links" aria-label="Navegación principal">
                <a href="#beneficios">Beneficios</a>
                <a href="#como-funciona">Cómo funciona</a>
                <a href="#plataforma">Plataforma</a>
                <a href="#crecimiento">Crecimiento</a>
            </nav>
            <a class="vx-btn vx-btn-primary" href="{{ url('/admin/login') }}">Entrar a VIRALYX <span>→</span></a>
        </div>
    </header>

    <main id="inicio">
        <section class="vx-hero">
            <div class="vx-hero-grid"></div>
            <div class="vx-container">
                <div class="vx-hero-content">
                    <span class="vx-eyebrow">Marketing digital que trabaja contigo</span>
                    <h1>Haz que tu negocio sea <span class="vx-gradient">imposible de ignorar.</span></h1>
                    <p>VIRALYX convierte la gestión de tus redes sociales en una estrategia de crecimiento: crea, organiza, programa y lleva tu contenido a donde están tus clientes.</p>
                    <div class="vx-hero-actions">
                        <a class="vx-btn vx-btn-primary" href="{{ url('/admin/login') }}">Comenzar a crecer <span>↗</span></a>
                        <a class="vx-btn vx-btn-ghost" href="#beneficios">Descubrir cómo funciona</a>
                    </div>
                    <div class="vx-trust">
                        <span>✓ <b>Más visibilidad</b> para tu marca</span>
                        <span>✓ <b>Menos trabajo manual</b></span>
                        <span>✓ <b>Contenido siempre activo</b></span>
                    </div>
                </div>
            </div>
        </section>

        <section id="beneficios" class="vx-section vx-section-dark">
            <div class="vx-container">
                <div class="vx-section-head">
                    <span class="vx-kicker">Una nueva forma de hacer marketing</span>
                    <h2>No publiques por publicar. <span class="vx-gradient">Publica para crecer.</span></h2>
                    <p>La presencia digital de un negocio no puede depender de la memoria, del tiempo libre o de abrir una red social cada vez que quieras comunicar algo.</p>
                </div>
                <div class="vx-grid-3">
                    <article class="vx-card">
                        <div class="vx-icon">◈</div>
                        <h3>Todo tu contenido, organizado</h3>
                        <p>Centraliza publicaciones, imágenes, mensajes y destinos en un flujo de trabajo pensado para que <strong>nunca pierdas una oportunidad de comunicar.</strong></p>
                    </article>
                    <article class="vx-card">
                        <div class="vx-icon">◷</div>
                        <h3>Programa y automatiza</h3>
                        <p>Prepara tu contenido con anticipación y deja que la plataforma haga el trabajo repetitivo. <strong>Tu marca sigue activa aunque tú estés ocupado.</strong></p>
                    </article>
                    <article class="vx-card">
                        <div class="vx-icon">↗</div>
                        <h3>Multiplica tu alcance</h3>
                        <p>Lleva cada mensaje a los canales adecuados y construye una presencia consistente que ayude a <strong>convertir atención en oportunidades.</strong></p>
                    </article>
                    <article class="vx-card">
                        <div class="vx-icon">◎</div>
                        <h3>Una visión de conjunto</h3>
                        <p>Deja atrás hojas de cálculo, notas dispersas y publicaciones improvisadas. <strong>Ten el control desde un solo lugar.</strong></p>
                    </article>
                    <article class="vx-card">
                        <div class="vx-icon">⌁</div>
                        <h3>Constancia que genera confianza</h3>
                        <p>Una comunicación regular hace que tu negocio permanezca presente cuando el cliente <strong>necesita elegir dónde comprar.</strong></p>
                    </article>
                    <article class="vx-card">
                        <div class="vx-icon">✦</div>
                        <h3>Creado para crecer contigo</h3>
                        <p>Empieza gestionando tus publicaciones y evoluciona hacia una operación digital más completa a medida que <strong>tu negocio aumenta su alcance.</strong></p>
                    </article>
                </div>
            </div>
        </section>

        <section class="vx-parallax vx-parallax-1">
            <div class="vx-container">
                <div class="vx-parallax-content">
                    <span class="vx-kicker">Tu contenido. Tu estrategia. Tu momento.</span>
                    <h2>La tecnología se encarga de la rutina. <span class="vx-gradient">Tú te enfocas en vender.</span></h2>
                    <p>Convierte horas de tareas repetitivas en minutos de planificación. Diseña tu comunicación, programa tus publicaciones y deja que VIRALYX mantenga el ritmo.</p>
                </div>
            </div>
        </section>

        <section id="como-funciona" class="vx-section vx-section-soft">
            <div class="vx-container">
                <div class="vx-section-head">
                    <span class="vx-kicker">Simple por diseño</span>
                    <h2>De una idea a una publicación <span class="vx-gradient">en cuatro pasos.</span></h2>
                    <p>VIRALYX convierte la planificación de contenidos en un proceso claro, repetible y fácil de administrar.</p>
                </div>
                <div class="vx-flow">
                    <div class="vx-step"><div class="vx-step-num">01</div><h3>Crea</h3><p>Define el mensaje, imagen y objetivo de cada publicación.</p></div>
                    <div class="vx-step"><div class="vx-step-num">02</div><h3>Organiza</h3><p>Ordena tu contenido y decide dónde y cuándo debe aparecer.</p></div>
                    <div class="vx-step"><div class="vx-step-num">03</div><h3>Programa</h3><p>Planifica tu calendario y automatiza las tareas repetitivas.</p></div>
                    <div class="vx-step"><div class="vx-step-num">04</div><h3>Crece</h3><p>Mantén una presencia constante y convierte más atención en negocio.</p></div>
                </div>
            </div>
        </section>

        <section id="plataforma" class="vx-section vx-section-dark">
            <div class="vx-container">
                <div class="vx-section-head">
                    <span class="vx-kicker">Control centralizado</span>
                    <h2>Tu operación de marketing, <span class="vx-gradient">en una sola plataforma.</span></h2>
                    <p>Una experiencia diseñada para que tengas claridad sobre qué publicar, cuándo hacerlo y cómo mantener activa la comunicación de tu negocio.</p>
                </div>
                <div class="vx-dashboard" aria-label="Vista conceptual del panel de VIRALYX">
                    <div class="vx-windowbar"><i class="vx-dot"></i><i class="vx-dot"></i><i class="vx-dot"></i></div>
                    <div class="vx-dashboard-body">
                        <aside class="vx-sidebar">
                            <div class="vx-side-item active">◈ Publicaciones</div>
                            <div class="vx-side-item">▦ Calendario</div>
                            <div class="vx-side-item">◎ Canales</div>
                            <div class="vx-side-item">↗ Resultados</div>
                            <div class="vx-side-item">⚙ Configuración</div>
                        </aside>
                        <div class="vx-dash-main">
                            <div class="vx-dash-title"><h3>Resumen de contenido</h3><span class="vx-pill">OPERACIÓN ACTIVA</span></div>
                            <div class="vx-mini-grid">
                                <div class="vx-mini"><small>Publicaciones programadas</small><strong>24</strong></div>
                                <div class="vx-mini"><small>Canales conectados</small><strong>06</strong></div>
                                <div class="vx-mini"><small>Contenido activo</small><strong>18</strong></div>
                            </div>
                            <div class="vx-chart" aria-hidden="true"></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="vx-parallax vx-parallax-2">
            <div class="vx-container">
                <div class="vx-parallax-content">
                    <span class="vx-kicker">Una marca que permanece presente</span>
                    <h2>Mientras otros improvisan, <span class="vx-gradient">tu negocio comunica.</span></h2>
                    <p>La constancia no significa publicar más por publicar. Significa estar presente con intención, construir reconocimiento y hacer que tu marca sea recordada.</p>
                </div>
            </div>
        </section>

        <section class="vx-section vx-section-soft">
            <div class="vx-container">
                <div class="vx-stats">
                    <div class="vx-stat"><strong class="vx-gradient">1</strong><span>centro de control</span></div>
                    <div class="vx-stat"><strong class="vx-gradient">24/7</strong><span>capacidad de planificación</span></div>
                    <div class="vx-stat"><strong class="vx-gradient">∞</strong><span>ideas para convertir en contenido</span></div>
                    <div class="vx-stat"><strong class="vx-gradient">↑</strong><span>visibilidad con estrategia</span></div>
                </div>
            </div>
        </section>

        <section id="crecimiento" class="vx-parallax vx-parallax-3">
            <div class="vx-container">
                <div class="vx-parallax-content">
                    <span class="vx-kicker">Marketing que trabaja por tu negocio</span>
                    <h2>Más presencia. Más oportunidades. <span class="vx-gradient">Más crecimiento.</span></h2>
                    <p>Tu cliente ya está conectado. La pregunta es si tu negocio está presente cuando busca, compara y decide. VIRALYX te ayuda a construir esa presencia todos los días.</p>
                </div>
            </div>
        </section>

        <section class="vx-cta">
            <div class="vx-container">
                <div class="vx-cta-inner">
                    <span class="vx-eyebrow">El siguiente paso empieza aquí</span>
                    <h2>Deja de perseguir clientes. <span class="vx-gradient">Haz que te encuentren.</span></h2>
                    <p>Transforma tu gestión de redes en un sistema de comunicación constante, organizado y preparado para acompañar el crecimiento de tu negocio.</p>
                    <a class="vx-btn vx-btn-primary" href="{{ url('/admin/login') }}">Entrar a VIRALYX y empezar <span>↗</span></a>
                </div>
            </div>
        </section>
    </main>

    <footer class="vx-footer">
        <div class="vx-container vx-footer-inner">
            <span class="vx-footer-brand">VIRALYX</span>
            <span>Marketing digital, automatización y crecimiento.</span>
            <span>© {{ date('Y') }} VIRALYX</span>
        </div>
    </footer>
</div>
</body>
</html>
