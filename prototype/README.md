# Prototipo de interfaz

Prototipo navegable de la tienda (HTML, JS y Tailwind por CDN). Usa **datos de ejemplo**: no hay backend, ningún pago se procesa y las políticas de envío y devolución son de muestra.

## Cómo verlo

Abre `index.html` en el navegador. Necesita conexión a internet para cargar Tailwind y las tipografías.

Arriba hay un selector **Ver como** para cambiar entre los tres perfiles:

| Perfil | Qué ve |
|---|---|
| Cliente | Inicio, catálogo con filtros, vista rápida, ficha, carrito, checkout y su cuenta |
| Administrador | Resumen de ventas, pedidos con estado editable e inventario |
| Super admin | Lo anterior, más usuarios y roles, pasarelas y tarifas de envío, y registro de actividad |

## Qué es y qué no es

- Es la referencia visual y de comportamiento para construir las vistas Blade y los componentes Livewire.
- No se copia tal cual al proyecto: los datos vendrán de la base de datos y la lógica de filtros, carrito y checkout vivirá en Livewire y en los servicios.
- Copia publicada (privada) del mismo prototipo: https://claude.ai/artifact/PYebuAsWAfE8mtNqCErdFS

## Decisiones de diseño

- Paleta: blanco, negro y un verde neón suave. El color fuerte lo ponen las portadas.
- Carátula de marca a pantalla completa en el inicio, que se levanta con el scroll.
- Despliegues animados (checkout, filtros, preguntas frecuentes, pedidos) y transiciones de bloque ligadas al scroll, con la curva `cubic-bezier(.33,1,.68,1)`.
- Con `prefers-reduced-motion` se desactivan las animaciones de entrada.
