<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import { home } from '@/routes';
</script>

<template>
    <Head title="Politica de Privacidad" />

    <div class="min-h-svh bg-background">
        <header class="sticky top-0 z-10 border-b bg-background/95 backdrop-blur-sm">
            <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-3 sm:px-6">
                <Link :href="home()">
                    <AppLogo />
                </Link>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-12">
            <h1 class="mb-2 text-2xl font-bold sm:text-3xl">Politica de Privacidad</h1>
            <p class="mb-8 text-sm text-muted-foreground">Ultima actualizacion: 14 de marzo de 2026</p>

            <div class="space-y-8 text-sm leading-relaxed text-muted-foreground">
                <p class="text-sm text-foreground">
                    <strong>Responsable del tratamiento:</strong> Wudok SAS &mdash; NIT: 901.664.651-2<br />
                    <strong>Domicilio:</strong> Cra. 49C No. 75-47, Nte. Centro Historico, Barranquilla, Atlantico, Colombia<br />
                    <strong>Correo electronico:</strong> privacidad@grandesdelfutbol.com<br />
                    <strong>Sitio web:</strong> https://grandesdelfutbol.com
                </p>

                <section>
                    <h2 class="mb-3 text-base font-semibold text-foreground sm:text-lg">1. Marco legal aplicable</h2>
                    <p>La presente Politica de Privacidad se rige por la legislacion colombiana vigente en materia de proteccion de datos personales:</p>
                    <ul class="ml-4 mt-2 list-disc space-y-1">
                        <li><strong>Constitucion Politica de Colombia</strong>, Articulo 15 &mdash; Derecho a la intimidad y al habeas data.</li>
                        <li><strong>Ley Estatutaria 1581 de 2012</strong> &mdash; Regimen General de Proteccion de Datos Personales.</li>
                        <li><strong>Decreto 1377 de 2013</strong> (incorporado en el Decreto Unico Reglamentario 1074 de 2015) &mdash; Reglamentario de la Ley 1581 de 2012.</li>
                        <li><strong>Ley 1266 de 2008</strong> &mdash; Disposiciones generales del habeas data.</li>
                        <li><strong>Ley 527 de 1999</strong> &mdash; Comercio electronico y firmas digitales.</li>
                        <li><strong>Circular Externa 002 de 2015 de la SIC</strong> &mdash; Guia de Responsabilidad Demostrada (Accountability).</li>
                        <li><strong>Ley 2300 de 2023</strong> &mdash; Regulacion de comunicaciones comerciales no solicitadas.</li>
                        <li><strong>Ley 1098 de 2006</strong> &mdash; Codigo de la Infancia y la Adolescencia.</li>
                    </ul>
                </section>

                <section>
                    <h2 class="mb-3 text-base font-semibold text-foreground sm:text-lg">2. Definiciones</h2>
                    <p>Conforme al Articulo 3 de la Ley 1581 de 2012:</p>
                    <ul class="ml-4 mt-2 list-disc space-y-1">
                        <li><strong>Titular:</strong> Persona natural cuyos datos personales sean objeto de tratamiento (el usuario de la aplicacion).</li>
                        <li><strong>Responsable del tratamiento:</strong> Wudok SAS, quien decide sobre la base de datos y/o el tratamiento de los datos.</li>
                        <li><strong>Encargado del tratamiento:</strong> Persona natural o juridica que realice el tratamiento de datos personales por cuenta del Responsable.</li>
                        <li><strong>Dato personal:</strong> Cualquier informacion vinculada o que pueda asociarse a una persona natural determinada o determinable.</li>
                        <li><strong>Dato sensible:</strong> Dato que afecta la intimidad del titular o cuyo uso indebido puede generar discriminacion.</li>
                        <li><strong>Tratamiento:</strong> Cualquier operacion sobre datos personales: recoleccion, almacenamiento, uso, circulacion, supresion.</li>
                        <li><strong>Autorizacion:</strong> Consentimiento previo, expreso e informado del titular para llevar a cabo el tratamiento de datos personales.</li>
                    </ul>
                </section>

                <section>
                    <h2 class="mb-3 text-base font-semibold text-foreground sm:text-lg">3. Datos que recopilamos</h2>

                    <h3 class="mb-2 mt-4 text-sm font-semibold text-foreground">3.1 Datos proporcionados directamente por el usuario</h3>
                    <div class="overflow-x-auto">
                        <table class="mt-2 w-full text-left text-sm">
                            <thead>
                                <tr class="border-b text-foreground">
                                    <th class="pb-2 pr-4 font-semibold">Dato</th>
                                    <th class="pb-2 pr-4 font-semibold">Tipo</th>
                                    <th class="pb-2 font-semibold">Finalidad</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr><td class="py-2 pr-4">Nombre completo</td><td class="py-2 pr-4">Personal</td><td class="py-2">Identificacion en la plataforma, perfil de jugador</td></tr>
                                <tr><td class="py-2 pr-4">Correo electronico</td><td class="py-2 pr-4">Personal</td><td class="py-2">Autenticacion, comunicaciones, recuperacion de cuenta</td></tr>
                                <tr><td class="py-2 pr-4">Fotografia de perfil</td><td class="py-2 pr-4">Personal / Imagen</td><td class="py-2">Identificacion visual en equipos y estadisticas</td></tr>
                                <tr><td class="py-2 pr-4">Numero de telefono</td><td class="py-2 pr-4">Personal</td><td class="py-2">Comunicaciones del club, notificaciones</td></tr>
                                <tr><td class="py-2 pr-4">Posicion de juego</td><td class="py-2 pr-4">No sensible</td><td class="py-2">Estadisticas deportivas</td></tr>
                                <tr><td class="py-2 pr-4">Nombre de equipo/club</td><td class="py-2 pr-4">No sensible</td><td class="py-2">Organizacion deportiva</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <h3 class="mb-2 mt-4 text-sm font-semibold text-foreground">3.2 Datos recopilados automaticamente</h3>
                    <div class="overflow-x-auto">
                        <table class="mt-2 w-full text-left text-sm">
                            <thead>
                                <tr class="border-b text-foreground">
                                    <th class="pb-2 pr-4 font-semibold">Dato</th>
                                    <th class="pb-2 pr-4 font-semibold">Tipo</th>
                                    <th class="pb-2 font-semibold">Finalidad</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr><td class="py-2 pr-4">Direccion IP</td><td class="py-2 pr-4">Personal</td><td class="py-2">Seguridad, prevencion de fraude</td></tr>
                                <tr><td class="py-2 pr-4">Tipo de navegador y dispositivo</td><td class="py-2 pr-4">Tecnico</td><td class="py-2">Optimizacion de la plataforma</td></tr>
                                <tr><td class="py-2 pr-4">Cookies y tecnologias similares</td><td class="py-2 pr-4">Tecnico</td><td class="py-2">Funcionamiento de la sesion</td></tr>
                                <tr><td class="py-2 pr-4">Datos de uso de la aplicacion</td><td class="py-2 pr-4">Tecnico</td><td class="py-2">Mejora del servicio</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <h3 class="mb-2 mt-4 text-sm font-semibold text-foreground">3.3 Datos obtenidos mediante Google OAuth</h3>
                    <p>Al iniciar sesion con Google, recibimos exclusivamente:</p>
                    <ul class="ml-4 mt-2 list-disc space-y-1">
                        <li>Nombre del perfil de Google</li>
                        <li>Direccion de correo electronico</li>
                        <li>Fotografia de perfil (si esta disponible)</li>
                        <li>Identificador unico de Google (no es la contrasena)</li>
                    </ul>
                    <p class="mt-2"><strong>NO</strong> accedemos a: contrasenas, contactos, correos, Drive, calendario ni ningun otro servicio de Google.</p>

                    <h3 class="mb-2 mt-4 text-sm font-semibold text-foreground">3.4 Datos sensibles</h3>
                    <p><strong>Grandes del Futbol NO recopila datos sensibles</strong> de forma deliberada. No solicitamos ni tratamos datos sobre origen racial o etnico, orientacion politica o religiosa, estado de salud o vida sexual, datos biometricos ni afiliacion sindical.</p>

                    <h3 class="mb-2 mt-4 text-sm font-semibold text-foreground">3.5 Datos de menores de edad</h3>
                    <p>Conforme al Articulo 7 de la Ley 1581 de 2012, el tratamiento de datos de ninos, ninas y adolescentes requiere autorizacion del representante legal, debe responder al interes superior del menor y se debe asegurar el respeto de sus derechos fundamentales.</p>
                </section>

                <section>
                    <h2 class="mb-3 text-base font-semibold text-foreground sm:text-lg">4. Finalidades del tratamiento</h2>

                    <h3 class="mb-2 mt-4 text-sm font-semibold text-foreground">4.1 Finalidades necesarias para la prestacion del servicio</h3>
                    <ol class="ml-4 list-decimal space-y-1">
                        <li>Crear y gestionar la cuenta de usuario.</li>
                        <li>Autenticar la identidad del usuario mediante Google OAuth.</li>
                        <li>Registrar y mostrar estadisticas deportivas individuales y colectivas.</li>
                        <li>Permitir la organizacion de clubes, equipos y partidos.</li>
                        <li>Asociar videos de YouTube a los partidos registrados.</li>
                        <li>Generar perfiles publicos de jugador dentro de la plataforma.</li>
                        <li>Enviar comunicaciones transaccionales (confirmacion de registro, cambios de cuenta, notificaciones de partidos).</li>
                    </ol>

                    <h3 class="mb-2 mt-4 text-sm font-semibold text-foreground">4.2 Finalidades adicionales (con consentimiento)</h3>
                    <ol class="ml-4 list-decimal space-y-1" start="8">
                        <li>Enviar comunicaciones promocionales sobre nuevas funcionalidades o eventos deportivos.</li>
                        <li>Compartir estadisticas o logros deportivos en redes sociales (previa autorizacion del titular).</li>
                        <li>Realizar estudios internos sobre uso de la plataforma para mejora del servicio.</li>
                        <li>Enviar notificaciones sobre inscripciones abiertas, carga de estadisticas o disponibilidad de videos.</li>
                    </ol>
                    <p class="mt-2">El titular podra negar su consentimiento a las finalidades adicionales (8 a 11) sin que ello afecte la prestacion del servicio principal.</p>
                </section>

                <section>
                    <h2 class="mb-3 text-base font-semibold text-foreground sm:text-lg">5. Autorizacion y consentimiento</h2>

                    <h3 class="mb-2 mt-4 text-sm font-semibold text-foreground">5.1 Forma de obtencion</h3>
                    <ul class="ml-4 list-disc space-y-1">
                        <li><strong>Al registrarse:</strong> Mediante la aceptacion expresa de esta Politica de Privacidad (checkbox obligatorio, no premarcado).</li>
                        <li><strong>Al iniciar sesion con Google:</strong> El usuario autoriza la transferencia de datos desde Google a Grandes del Futbol.</li>
                        <li><strong>Conducta inequivoca:</strong> El uso continuado de la plataforma despues de haber sido informado constituye conducta inequivoca de autorizacion.</li>
                    </ul>

                    <h3 class="mb-2 mt-4 text-sm font-semibold text-foreground">5.2 Prueba de la autorizacion</h3>
                    <p>Wudok SAS conservara prueba de la autorizacion otorgada por los titulares. Se almacenara: fecha y hora de aceptacion, version de la politica aceptada, direccion IP e identificador del usuario.</p>

                    <h3 class="mb-2 mt-4 text-sm font-semibold text-foreground">5.3 Revocatoria de la autorizacion</h3>
                    <p>El titular podra revocar la autorizacion en cualquier momento, mediante solicitud dirigida a privacidad@grandesdelfutbol.com, conforme al Articulo 8 de la Ley 1581 de 2012.</p>
                </section>

                <section>
                    <h2 class="mb-3 text-base font-semibold text-foreground sm:text-lg">6. Derechos del titular</h2>
                    <p>Conforme al Articulo 8 de la Ley 1581 de 2012, el titular tiene derecho a:</p>
                    <ol class="ml-4 mt-2 list-decimal space-y-1">
                        <li><strong>Conocer:</strong> Acceder a sus datos personales que hayan sido objeto de tratamiento.</li>
                        <li><strong>Actualizar:</strong> Solicitar la actualizacion de datos inexactos o incompletos.</li>
                        <li><strong>Rectificar:</strong> Solicitar la correccion de informacion inexacta.</li>
                        <li><strong>Suprimir:</strong> Solicitar la eliminacion de datos cuando no exista obligacion legal que lo impida.</li>
                        <li><strong>Revocar:</strong> Revocar la autorizacion otorgada para el tratamiento.</li>
                        <li><strong>Acceder gratuitamente:</strong> A sus datos personales al menos una vez cada mes calendario.</li>
                        <li><strong>Presentar quejas:</strong> Ante la Superintendencia de Industria y Comercio (SIC).</li>
                    </ol>

                    <h3 class="mb-2 mt-4 text-sm font-semibold text-foreground">6.1 Procedimiento para ejercer derechos</h3>
                    <ul class="ml-4 list-disc space-y-1">
                        <li><strong>Canal:</strong> Enviar solicitud a privacidad@grandesdelfutbol.com</li>
                        <li><strong>Identificacion:</strong> Incluir nombre completo, documento de identidad, datos de contacto y descripcion del derecho que desea ejercer.</li>
                        <li><strong>Plazo de respuesta:</strong> Consultas: 10 dias habiles (prorrogables 5). Reclamos: 15 dias habiles (prorrogables 8).</li>
                    </ul>
                </section>

                <section>
                    <h2 class="mb-3 text-base font-semibold text-foreground sm:text-lg">7. Deberes de Wudok SAS como responsable</h2>
                    <p>Conforme al Articulo 17 de la Ley 1581 de 2012:</p>
                    <ol class="ml-4 mt-2 list-decimal space-y-1">
                        <li>Garantizar al titular el ejercicio del derecho de habeas data.</li>
                        <li>Solicitar y conservar copia de la autorizacion otorgada por el titular.</li>
                        <li>Informar debidamente al titular sobre la finalidad de la recoleccion y los derechos que le asisten.</li>
                        <li>Conservar la informacion bajo las condiciones de seguridad necesarias.</li>
                        <li>Rectificar la informacion cuando sea incorrecta y comunicar lo pertinente.</li>
                        <li>Tramitar las consultas y reclamos en los terminos de ley.</li>
                        <li>Adoptar un manual interno de politicas y procedimientos.</li>
                        <li>Informar a la autoridad de proteccion de datos cuando se presenten violaciones a los codigos de seguridad.</li>
                    </ol>
                </section>

                <section>
                    <h2 class="mb-3 text-base font-semibold text-foreground sm:text-lg">8. Transferencia y transmision de datos</h2>

                    <h3 class="mb-2 mt-4 text-sm font-semibold text-foreground">8.1 Transmision a encargados</h3>
                    <div class="overflow-x-auto">
                        <table class="mt-2 w-full text-left text-sm">
                            <thead>
                                <tr class="border-b text-foreground">
                                    <th class="pb-2 pr-4 font-semibold">Encargado</th>
                                    <th class="pb-2 pr-4 font-semibold">Pais</th>
                                    <th class="pb-2 font-semibold">Finalidad</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr><td class="py-2 pr-4">Google LLC (OAuth, Analytics)</td><td class="py-2 pr-4">Estados Unidos</td><td class="py-2">Autenticacion, analitica web</td></tr>
                                <tr><td class="py-2 pr-4">Proveedor de hosting (Contabo)</td><td class="py-2 pr-4">Alemania</td><td class="py-2">Almacenamiento y procesamiento</td></tr>
                                <tr><td class="py-2 pr-4">YouTube (Google LLC)</td><td class="py-2 pr-4">Estados Unidos</td><td class="py-2">Visualizacion de videos de partidos</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <h3 class="mb-2 mt-4 text-sm font-semibold text-foreground">8.2 Transferencia internacional</h3>
                    <p>La transferencia internacional de datos se realizara conforme al Articulo 26 de la Ley 1581 de 2012.</p>
                    <p class="mt-2"><strong>NO vendemos, alquilamos ni comercializamos datos personales a terceros.</strong></p>
                </section>

                <section>
                    <h2 class="mb-3 text-base font-semibold text-foreground sm:text-lg">9. Medidas de seguridad</h2>
                    <p>Conforme al principio de seguridad (Art. 4, literal g, Ley 1581 de 2012):</p>
                    <ul class="ml-4 mt-2 list-disc space-y-1">
                        <li>Cifrado de datos en transito (TLS/HTTPS).</li>
                        <li>Cifrado de contrasenas con algoritmos de hash seguros (bcrypt).</li>
                        <li>Autenticacion delegada a Google OAuth (no almacenamos contrasenas de Google).</li>
                        <li>Control de acceso basado en roles.</li>
                        <li>Respaldos periodicos de la base de datos.</li>
                        <li>Monitoreo de accesos no autorizados.</li>
                        <li>Principio de minimizacion: solo recopilamos datos estrictamente necesarios.</li>
                    </ul>
                    <p class="mt-2">En caso de incidentes de seguridad, se notificara a los titulares afectados y a la Superintendencia de Industria y Comercio (SIC).</p>
                </section>

                <section>
                    <h2 class="mb-3 text-base font-semibold text-foreground sm:text-lg">10. Cookies y tecnologias similares</h2>
                    <ul class="ml-4 list-disc space-y-1">
                        <li><strong>Cookies esenciales:</strong> Mantener la sesion del usuario activa. No requieren consentimiento.</li>
                        <li><strong>Cookies de rendimiento:</strong> Analitica de uso (Google Analytics). Requieren consentimiento.</li>
                    </ul>
                    <p class="mt-2">El usuario puede configurar su navegador para rechazar cookies no esenciales. La desactivacion de cookies esenciales puede afectar el funcionamiento de la plataforma.</p>
                </section>

                <section>
                    <h2 class="mb-3 text-base font-semibold text-foreground sm:text-lg">11. Registro Nacional de Bases de Datos (RNBD)</h2>
                    <p>Conforme al Articulo 25 de la Ley 1581 de 2012 y el Decreto 886 de 2014, Wudok SAS registrara las bases de datos que contengan datos personales ante el Registro Nacional de Bases de Datos (RNBD) administrado por la Superintendencia de Industria y Comercio (SIC).</p>
                </section>

                <section>
                    <h2 class="mb-3 text-base font-semibold text-foreground sm:text-lg">12. Vigencia y cambios</h2>
                    <ul class="ml-4 list-disc space-y-1">
                        <li>Esta politica entra en vigencia el 14 de marzo de 2026.</li>
                        <li>Wudok SAS se reserva el derecho de modificar esta politica en cualquier momento.</li>
                        <li>Los cambios seran notificados con al menos 5 dias habiles de anticipacion.</li>
                        <li>Las versiones anteriores estaran disponibles para consulta.</li>
                    </ul>
                </section>

                <section>
                    <h2 class="mb-3 text-base font-semibold text-foreground sm:text-lg">13. Contacto y canal de atencion</h2>
                    <p>
                        <strong>Responsable del tratamiento:</strong> Wudok SAS<br />
                        <strong>Oficial de Proteccion de Datos:</strong> Maximo Miranda<br />
                        <strong>Correo:</strong> privacidad@grandesdelfutbol.com<br />
                        <strong>Direccion:</strong> Cra. 49C No. 75-47, Nte. Centro Historico, Barranquilla, Atlantico, Colombia<br />
                        <strong>Telefono:</strong> +57 305 282 7394
                    </p>
                    <p class="mt-4">
                        <strong>Autoridad de control:</strong><br />
                        Superintendencia de Industria y Comercio (SIC)<br />
                        Delegatura para la Proteccion de Datos Personales<br />
                        Carrera 13 No. 27-00, Pisos 1 al 6, Bogota D.C.<br />
                        Telefono: (601) 587 0000 &mdash; www.sic.gov.co
                    </p>
                </section>
            </div>
        </main>
    </div>
</template>
