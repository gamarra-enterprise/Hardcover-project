<x-shop-layout title="Nosotros">
    <div class="wrap" style="padding-block: 28px 0; max-width: 760px">
        <nav class="muted" style="font-size: 13px" aria-label="Ruta"><a href="{{ route('home') }}">Inicio</a> / Nosotros</nav>
        <h1 style="font-size: clamp(2.2rem, 5vw, 3.4rem); font-weight: 800; letter-spacing: -.035em; margin-top: .3rem">Nosotros</h1>

        <div class="prose-block">
            <p>Hardcover Bookery es una librería independiente de Lima. Vendemos libros, papelería y pequeños objetos para lectores, y enviamos tus compras con seguimiento.</p>
            <p>En esta web puedes explorar el catálogo, guardar lo que te interesa en tu carrito y recibir un aviso por correo cada vez que cambie el estado de tu pedido.</p>
        </div>

        <div style="display: flex; gap: .7rem; flex-wrap: wrap; margin-top: 1.4rem">
            <a class="btn btn-dark" href="{{ route('catalog') }}">Ver el catálogo</a>
            <a class="btn" href="https://www.instagram.com/hardcoverbookery" target="_blank" rel="noopener">@hardcoverbookery</a>
        </div>
    </div>
</x-shop-layout>
