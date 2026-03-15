<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import { home, privacy } from '@/routes';
</script>

<template>
    <Head title="Terminos y Condiciones de Uso" />

    <div class="min-h-svh bg-background">
        <header class="sticky top-0 z-10 border-b bg-background/95 backdrop-blur-sm">
            <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-3 sm:px-6">
                <Link :href="home()">
                    <AppLogo />
                </Link>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-12">
            <h1 class="mb-2 text-2xl font-bold sm:text-3xl">Terminos y Condiciones de Uso</h1>
            <p class="mb-8 text-sm text-muted-foreground">Ultima actualizacion: 14 de marzo de 2026</p>

            <div class="space-y-8 text-sm leading-relaxed text-muted-foreground">
                <p class="text-sm text-foreground">
                    <strong>Proveedor del servicio:</strong> Wudok SAS &mdash; NIT: 901.664.651-2<br />
                    <strong>Domicilio:</strong> Cra. 49C No. 75-47, Nte. Centro Historico, Barranquilla, Atlantico, Colombia<br />
                    <strong>Sitio web:</strong> https://grandesdelfutbol.com
                </p>

                <section>
                    <h2 class="mb-3 text-base font-semibold text-foreground sm:text-lg">1. Marco legal</h2>
                    <p>Los presentes Terminos y Condiciones se rigen por:</p>
                    <ul class="ml-4 mt-2 list-disc space-y-1">
                        <li><strong>Constitucion Politica de Colombia</strong>, Articulos 15 (intimidad), 20 (informacion) y 61 (propiedad intelectual).</li>
                        <li><strong>Ley 527 de 1999</strong> &mdash; Comercio electronico, mensajes de datos y firmas digitales.</li>
                        <li><strong>Ley 1480 de 2011</strong> &mdash; Estatuto del Consumidor.</li>
                        <li><strong>Ley 1581 de 2012</strong> &mdash; Proteccion de Datos Personales.</li>
                        <li><strong>Ley 23 de 1982</strong> y <strong>Decision Andina 351 de 1993</strong> &mdash; Derechos de autor.</li>
                        <li><strong>Ley 1341 de 2009</strong> (modificada por la <strong>Ley 1978 de 2019</strong>) &mdash; Ley de TIC.</li>
                        <li><strong>Decreto 1074 de 2015</strong> &mdash; Decreto Unico Reglamentario del sector comercio.</li>
                        <li><strong>Ley 2300 de 2023</strong> &mdash; Regulacion de comunicaciones comerciales.</li>
                    </ul>
                </section>

                <section>
                    <h2 class="mb-3 text-base font-semibold text-foreground sm:text-lg">2. Aceptacion de los terminos</h2>
                    <p>Al registrarse, acceder o utilizar la plataforma Grandes del Futbol ("la Plataforma"), el usuario ("el Usuario") acepta en su totalidad los presentes Terminos y Condiciones.</p>
                    <p class="mt-2">Si el Usuario no esta de acuerdo con alguna disposicion, debe abstenerse de usar la Plataforma.</p>
                    <p class="mt-2">El uso de la Plataforma implica que el Usuario:</p>
                    <ul class="ml-4 mt-2 list-disc space-y-1">
                        <li>Es mayor de 18 anos o cuenta con autorizacion de su representante legal.</li>
                        <li>Tiene capacidad legal para celebrar contratos vinculantes.</li>
                        <li>Acepta la <Link :href="privacy()" class="font-medium text-primary underline underline-offset-2">Politica de Privacidad</Link>.</li>
                    </ul>
                </section>

                <section>
                    <h2 class="mb-3 text-base font-semibold text-foreground sm:text-lg">3. Descripcion del servicio</h2>
                    <p>Grandes del Futbol es una plataforma web (PWA) que permite:</p>
                    <ol class="ml-4 mt-2 list-decimal space-y-1">
                        <li><strong>Gestion de clubes de futbol amateur:</strong> Creacion y administracion de clubes deportivos.</li>
                        <li><strong>Registro de partidos:</strong> Programacion, carga de resultados y estadisticas.</li>
                        <li><strong>Estadisticas de jugadores:</strong> Registro individual de goles, asistencias, faltas, tarjetas y otras metricas deportivas.</li>
                        <li><strong>Perfiles de jugador:</strong> Creacion de perfil con historial deportivo dentro de la plataforma.</li>
                        <li><strong>Integracion con YouTube:</strong> Asociacion de enlaces de video a partidos registrados.</li>
                        <li><strong>Torneos:</strong> Organizacion de competencias entre clubes (cuando este disponible).</li>
                    </ol>
                </section>

                <section>
                    <h2 class="mb-3 text-base font-semibold text-foreground sm:text-lg">4. Registro y cuenta de usuario</h2>

                    <h3 class="mb-2 mt-4 text-sm font-semibold text-foreground">4.1 Requisitos de registro</h3>
                    <ul class="ml-4 list-disc space-y-1">
                        <li>Proporcionar informacion veraz, completa y actualizada.</li>
                        <li>Crear una cuenta personal e intransferible.</li>
                        <li>El registro puede realizarse mediante correo electronico o mediante Google OAuth.</li>
                    </ul>

                    <h3 class="mb-2 mt-4 text-sm font-semibold text-foreground">4.2 Responsabilidad del usuario</h3>
                    <ul class="ml-4 list-disc space-y-1">
                        <li>El Usuario es responsable de mantener la confidencialidad de sus credenciales de acceso.</li>
                        <li>El Usuario debe notificar inmediatamente a Wudok SAS cualquier uso no autorizado de su cuenta.</li>
                        <li>Toda actividad realizada desde la cuenta del Usuario se presume realizada por este.</li>
                    </ul>

                    <h3 class="mb-2 mt-4 text-sm font-semibold text-foreground">4.3 Veracidad de la informacion</h3>
                    <p>Conforme al Articulo 7 de la Ley 527 de 1999, el Usuario garantiza que la informacion proporcionada es veraz y corresponde a su identidad real. El suministro de informacion falsa podra dar lugar a la cancelacion de la cuenta.</p>
                </section>

                <section>
                    <h2 class="mb-3 text-base font-semibold text-foreground sm:text-lg">5. Roles y responsabilidades</h2>

                    <h3 class="mb-2 mt-4 text-sm font-semibold text-foreground">5.1 Administrador de club</h3>
                    <ul class="ml-4 list-disc space-y-1">
                        <li>Es el usuario que crea un club dentro de la plataforma.</li>
                        <li>Es responsable de la veracidad de los datos del club y de los jugadores que registre.</li>
                        <li>Si registra menores de edad, debe contar con la autorizacion del representante legal conforme a la Ley 1098 de 2006 y la Ley 1581 de 2012.</li>
                        <li>Es responsable de gestionar las suscripciones del club (cuando aplique).</li>
                    </ul>

                    <h3 class="mb-2 mt-4 text-sm font-semibold text-foreground">5.2 Jugador</h3>
                    <ul class="ml-4 list-disc space-y-1">
                        <li>Usuario registrado que participa en partidos dentro de un club.</li>
                        <li>Es responsable de la veracidad de su informacion personal.</li>
                        <li>Acepta que sus estadisticas deportivas sean visibles para los miembros del club.</li>
                    </ul>

                    <h3 class="mb-2 mt-4 text-sm font-semibold text-foreground">5.3 Grabador/Videografo</h3>
                    <ul class="ml-4 list-disc space-y-1">
                        <li>Persona autorizada por el administrador del club para grabar partidos y capturar estadisticas.</li>
                        <li>Debe respetar el derecho de imagen de los participantes conforme a la legislacion colombiana.</li>
                    </ul>
                </section>

                <section>
                    <h2 class="mb-3 text-base font-semibold text-foreground sm:text-lg">6. Uso aceptable</h2>
                    <p>El Usuario se compromete a:</p>
                    <ol class="ml-4 mt-2 list-decimal space-y-1">
                        <li>Usar la Plataforma unicamente para los fines deportivos y recreativos para los que fue disenada.</li>
                        <li>No utilizar la Plataforma para actividades ilegales o contrarias a la moral y las buenas costumbres.</li>
                        <li>No intentar acceder a cuentas de otros usuarios.</li>
                        <li>No realizar ingenieria inversa, descompilar o desensamblar la Plataforma.</li>
                        <li>No cargar contenido que sea ilegal, difamatorio, obsceno, discriminatorio o que viole derechos de terceros.</li>
                        <li>No utilizar la Plataforma para enviar spam o comunicaciones masivas no solicitadas (conforme a la Ley 2300 de 2023).</li>
                        <li>No manipular estadisticas o resultados de partidos de forma fraudulenta.</li>
                        <li>No suplantar la identidad de otra persona.</li>
                    </ol>
                </section>

                <section>
                    <h2 class="mb-3 text-base font-semibold text-foreground sm:text-lg">7. Propiedad intelectual</h2>

                    <h3 class="mb-2 mt-4 text-sm font-semibold text-foreground">7.1 De la Plataforma</h3>
                    <p>Conforme a la Ley 23 de 1982 y la Decision Andina 351 de 1993:</p>
                    <ul class="ml-4 mt-2 list-disc space-y-1">
                        <li>El codigo fuente, diseno, logotipos, marcas, textos y demas elementos de la Plataforma son propiedad exclusiva de Wudok SAS.</li>
                        <li>Queda prohibida la reproduccion, distribucion o modificacion sin autorizacion escrita de Wudok SAS.</li>
                        <li>La marca "Grandes del Futbol" es propiedad de Wudok SAS.</li>
                    </ul>

                    <h3 class="mb-2 mt-4 text-sm font-semibold text-foreground">7.2 Del contenido generado por el usuario</h3>
                    <ul class="ml-4 list-disc space-y-1">
                        <li>Las estadisticas, nombres de clubes y datos deportivos ingresados por el Usuario son proporcionados bajo licencia de uso a favor de Wudok SAS para el funcionamiento de la Plataforma.</li>
                        <li>El Usuario conserva sus derechos sobre los datos personales conforme a la Ley 1581 de 2012.</li>
                        <li>Los enlaces de YouTube compartidos en la Plataforma son propiedad de sus respectivos autores y se rigen por los Terminos de Servicio de YouTube.</li>
                    </ul>

                    <h3 class="mb-2 mt-4 text-sm font-semibold text-foreground">7.3 Derecho de imagen</h3>
                    <ul class="ml-4 list-disc space-y-1">
                        <li>La publicacion de videos de partidos requiere el consentimiento de los participantes.</li>
                        <li>Al registrarse en un club que graba partidos, el Usuario autoriza la captura y publicacion de su imagen dentro del contexto deportivo.</li>
                        <li>El Usuario puede revocar esta autorizacion en cualquier momento, lo cual podra implicar la imposibilidad de participar en partidos grabados.</li>
                    </ul>
                </section>

                <section>
                    <h2 class="mb-3 text-base font-semibold text-foreground sm:text-lg">8. Planes y pagos</h2>

                    <h3 class="mb-2 mt-4 text-sm font-semibold text-foreground">8.1 Plan gratuito</h3>
                    <p>La Plataforma ofrece un plan basico gratuito con funcionalidades limitadas.</p>

                    <h3 class="mb-2 mt-4 text-sm font-semibold text-foreground">8.2 Plan de pago (cuando este disponible)</h3>
                    <ul class="ml-4 list-disc space-y-1">
                        <li>Los planes de pago se facturaran al administrador del club de forma mensual.</li>
                        <li>Los precios seran informados en pesos colombianos (COP) e incluiran el IVA correspondiente.</li>
                        <li>Los pagos se procesaran a traves de pasarelas de pago certificadas.</li>
                        <li>Las facturas electronicas cumpliran con la Resolucion 000165 de 2023 de la DIAN.</li>
                    </ul>

                    <h3 class="mb-2 mt-4 text-sm font-semibold text-foreground">8.3 Politica de reembolso</h3>
                    <ul class="ml-4 list-disc space-y-1">
                        <li>El Usuario podra solicitar reembolso dentro de los primeros 5 dias habiles posteriores al pago si no ha utilizado las funcionalidades premium.</li>
                        <li>Conforme al Articulo 47 de la Ley 1480 de 2011 (Estatuto del Consumidor), el derecho de retracto aplica dentro de los 5 dias habiles siguientes a la celebracion del contrato.</li>
                        <li>Pasado este plazo, no habra lugar a reembolso por el periodo ya pagado.</li>
                    </ul>

                    <h3 class="mb-2 mt-4 text-sm font-semibold text-foreground">8.4 Grabacion de partidos</h3>
                    <ul class="ml-4 list-disc space-y-1">
                        <li>El servicio de grabacion de partidos se contrata de forma independiente por partido.</li>
                        <li>El precio sera informado antes de la contratacion.</li>
                        <li>La entrega del video se realizara mediante un enlace de YouTube dentro de 48 horas posteriores al partido.</li>
                    </ul>
                </section>

                <section>
                    <h2 class="mb-3 text-base font-semibold text-foreground sm:text-lg">9. Disponibilidad del servicio</h2>
                    <ul class="ml-4 list-disc space-y-1">
                        <li>Wudok SAS hara esfuerzos razonables para mantener la Plataforma disponible, pero no garantiza disponibilidad ininterrumpida.</li>
                        <li>Podran existir interrupciones por mantenimiento, actualizaciones o causas de fuerza mayor.</li>
                        <li>Wudok SAS no sera responsable por danos derivados de interrupciones del servicio.</li>
                    </ul>
                </section>

                <section>
                    <h2 class="mb-3 text-base font-semibold text-foreground sm:text-lg">10. Limitacion de responsabilidad</h2>
                    <p>Wudok SAS:</p>
                    <ol class="ml-4 mt-2 list-decimal space-y-1">
                        <li><strong>No es responsable</strong> por la veracidad de las estadisticas ingresadas por los usuarios o grabadores.</li>
                        <li><strong>No es responsable</strong> por lesiones, danos fisicos o accidentes ocurridos durante los partidos de futbol.</li>
                        <li><strong>No es responsable</strong> por disputas entre miembros de un club.</li>
                        <li><strong>No garantiza</strong> resultados deportivos especificos.</li>
                        <li><strong>No es responsable</strong> por el contenido de los videos de YouTube enlazados desde la Plataforma.</li>
                        <li>En ningun caso la responsabilidad total de Wudok SAS excedera el valor pagado por el Usuario en los ultimos 12 meses.</li>
                    </ol>
                    <p class="mt-2">Conforme al Articulo 11 de la Ley 1480 de 2011, lo anterior no exime a Wudok SAS de responder por la calidad e idoneidad de los servicios efectivamente prestados.</p>
                </section>

                <section>
                    <h2 class="mb-3 text-base font-semibold text-foreground sm:text-lg">11. Suspension y terminacion</h2>

                    <h3 class="mb-2 mt-4 text-sm font-semibold text-foreground">11.1 Por parte del Usuario</h3>
                    <p>El Usuario puede cancelar su cuenta en cualquier momento desde la configuracion de su perfil o enviando solicitud a soporte@grandesdelfutbol.com. La cancelacion no genera derecho a reembolso del periodo en curso.</p>

                    <h3 class="mb-2 mt-4 text-sm font-semibold text-foreground">11.2 Por parte de Wudok SAS</h3>
                    <p>Wudok SAS podra suspender o cancelar cuentas que violen estos Terminos, utilicen la Plataforma para fines ilegales, proporcionen informacion falsa o realicen actividades que afecten el funcionamiento de la Plataforma. Se notificara al Usuario con al menos 5 dias habiles de anticipacion, salvo en casos de violacion grave.</p>

                    <h3 class="mb-2 mt-4 text-sm font-semibold text-foreground">11.3 Efectos de la terminacion</h3>
                    <ul class="ml-4 list-disc space-y-1">
                        <li>Los datos personales seran tratados conforme a la Politica de Privacidad.</li>
                        <li>Las estadisticas del jugador se anonimizaran o eliminaran, segun solicite el titular.</li>
                        <li>Las obligaciones de pago pendientes subsistiran.</li>
                    </ul>
                </section>

                <section>
                    <h2 class="mb-3 text-base font-semibold text-foreground sm:text-lg">12. Resolucion de conflictos</h2>
                    <ul class="ml-4 list-disc space-y-1">
                        <li><strong>Mecanismo directo:</strong> Las controversias se intentaran resolver primero mediante comunicacion directa a traves de soporte@grandesdelfutbol.com.</li>
                        <li><strong>Conciliacion:</strong> Si no se llega a un acuerdo, las partes podran acudir a un centro de conciliacion autorizado por el Ministerio de Justicia y del Derecho.</li>
                        <li><strong>SIC:</strong> Para asuntos de proteccion al consumidor, el Usuario podra acudir ante la Delegatura de Proteccion al Consumidor de la SIC.</li>
                        <li><strong>Jurisdiccion:</strong> Las partes se someten a la jurisdiccion de los jueces de Colombia, con domicilio en Barranquilla, Atlantico.</li>
                    </ul>
                </section>

                <section>
                    <h2 class="mb-3 text-base font-semibold text-foreground sm:text-lg">13. Comunicaciones</h2>
                    <p>Conforme a la Ley 2300 de 2023:</p>
                    <ul class="ml-4 mt-2 list-disc space-y-1">
                        <li>Las comunicaciones comerciales o promocionales solo se enviaran a usuarios que hayan otorgado su consentimiento previo.</li>
                        <li>Cada comunicacion incluira un mecanismo de desuscripcion.</li>
                        <li>Las comunicaciones transaccionales (confirmacion de cuenta, notificaciones de partidos) no requieren consentimiento adicional.</li>
                        <li>Las comunicaciones se realizaran en horarios razonables (8:00 AM a 8:00 PM, hora colombiana).</li>
                    </ul>
                </section>

                <section>
                    <h2 class="mb-3 text-base font-semibold text-foreground sm:text-lg">14. Modificaciones a los terminos</h2>
                    <ul class="ml-4 list-disc space-y-1">
                        <li>Wudok SAS podra modificar estos Terminos en cualquier momento.</li>
                        <li>Los cambios se comunicaran mediante aviso en la Plataforma y/o correo electronico con al menos 10 dias calendario de anticipacion.</li>
                        <li>El uso continuado de la Plataforma despues de la entrada en vigencia de los cambios constituira aceptacion de los mismos.</li>
                    </ul>
                </section>

                <section>
                    <h2 class="mb-3 text-base font-semibold text-foreground sm:text-lg">15. Disposiciones generales</h2>
                    <ul class="ml-4 list-disc space-y-1">
                        <li><strong>Ley aplicable:</strong> Estos Terminos se rigen exclusivamente por las leyes de la Republica de Colombia.</li>
                        <li><strong>Divisibilidad:</strong> Si alguna clausula es declarada nula o inaplicable, las demas conservaran su plena vigencia.</li>
                        <li><strong>No renuncia:</strong> El hecho de que Wudok SAS no ejerza algun derecho previsto en estos Terminos no constituira renuncia al mismo.</li>
                        <li><strong>Acuerdo completo:</strong> Estos Terminos, junto con la Politica de Privacidad, constituyen el acuerdo completo entre el Usuario y Wudok SAS.</li>
                    </ul>
                </section>

                <section>
                    <h2 class="mb-3 text-base font-semibold text-foreground sm:text-lg">16. Contacto</h2>
                    <p>
                        <strong>Proveedor:</strong> Wudok SAS<br />
                        <strong>Correo general:</strong> soporte@grandesdelfutbol.com<br />
                        <strong>Correo de privacidad:</strong> privacidad@grandesdelfutbol.com<br />
                        <strong>Direccion:</strong> Cra. 49C No. 75-47, Nte. Centro Historico, Barranquilla, Atlantico, Colombia<br />
                        <strong>Telefono:</strong> +57 305 282 7394
                    </p>
                </section>
            </div>
        </main>
    </div>
</template>
