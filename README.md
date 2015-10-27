# Pulsarcode Framework

Este Framework nació por la necesidad de implementar un MVC en un viejo código espagueti heredado resultado de la fusión
de 3 proyectos PHP (uno de ellos basado en Symfony 1.4).

Se diseño principalmente por las carencias básicas de algunos aspectos de cualquier código PHP heredado:

* Organizar los archivos PHP estructurados con PSR0 y PSR4 (estaban casi todos en raíz y en la carpeta /includes)
* Eliminar cien mil puntos de entrada estilo http://example.com/my-file.php
* Securizar los accesos a super globales (se usaba $_GET y $_POST sin sanitizar)
* Implementar un motor de plantillas con variables en lugar de mezclar HTML + PHP + includes
* Gestionar por completo el control de errores (en producción petaban cohetes y no se enteraba nadie)
* Implementar un sistema de caché sencillo basado en DoctrineCache
* Implementar un dispatcher encargado de servir todas las peticiones en lugar de procesarlas un .htaccess de 5000 líneas

El Framework tiene un acoplamiento excesivo con el código espagueti heredado, aunque es posible que se refactorice en un
futuro no muy lejano para poder liberar los componentes y ser mas flexible a la hora de implementarlos en cualquier otro
proyecto PHP.

El desarrollo de este Framework me sirvió de experimento para examinar a fondo algunos componentes de Symfony y algunos
comportamientos de PHP para la gestión de errores, si crees que alguna parte pueda resultarte útil de algún modo úsala.

No está terminado, ningún software lo está, está acoplado y puede no serte 100% funcional.

Larga vida a los petates.
