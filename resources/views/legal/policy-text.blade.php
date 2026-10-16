{{--
    Política de tratamiento de datos personales de NutriTrack (Ley 1581 de 2012 y Decreto 1074 de 2015).
    BORRADOR: debe revisarlo el área jurídica de cada institución antes de publicarlo.
    Los datos del responsable salen de config('security.privacy') (.env: DATA_CONTROLLER_NAME, DATA_CONTROLLER_NIT, DATA_PROTECTION_EMAIL).
--}}
@php $p = config('security.privacy'); @endphp
<div class="policy">
    <p class="policy-meta">Versión {{ $p['version'] }} · Vigente desde {{ \Carbon\Carbon::parse($p['version'])->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}</p>

    <h2>1. Responsable y encargado del tratamiento</h2>
    <p><strong>Responsable:</strong> {{ $p['controller'] }}, NIT {{ $p['controller_nit'] }}. Correo para asuntos de datos personales: {{ $p['contact_email'] }}.</p>
    <p><strong>Encargado:</strong> el proveedor de NutriTrack trata los datos por cuenta de la institución, solo para prestar el servicio de la plataforma y según las instrucciones del responsable.</p>

    <h2>2. Datos que se tratan</h2>
    <ul>
        <li><strong>Del personal usuario:</strong> nombre, correo, teléfono, rol, y registros de acceso y actividad (fecha, hora, dirección IP y navegador).</li>
        <li><strong>De los pacientes:</strong> nombre, habitación, prescripción dietaria (tiempos de comida, consistencia, especificaciones, observaciones, aislamiento) y su historial de cambios, despachos y recepciones.</li>
    </ul>
    <p>La información de salud es un <strong>dato sensible</strong> (art. 5 de la Ley 1581 de 2012). Su tratamiento requiere la autorización previa y expresa del titular o se ampara en las excepciones legales del art. 6, como la atención en salud. Obtener esa autorización corresponde a la institución.</p>

    <h2>3. Finalidades</h2>
    <ul>
        <li>Prescribir, versionar, despachar y verificar la entrega de las dietas de los pacientes hospitalizados.</li>
        <li>Generar los reportes de producción, nutrición y facturación del servicio de alimentación.</li>
        <li>Controlar el acceso, mantener la trazabilidad (auditoría) y proteger la seguridad de la información.</li>
    </ul>
    <p>Los datos no se usan con fines comerciales ni se comparten con terceros distintos del encargado, salvo por orden de autoridad competente.</p>

    <h2>4. Derechos de los titulares</h2>
    <p>Conocer, actualizar y rectificar sus datos; solicitar prueba de la autorización; ser informado del uso dado a sus datos; presentar quejas ante la Superintendencia de Industria y Comercio; revocar la autorización o pedir la supresión cuando no exista un deber legal o contractual de conservarlos; y acceder gratuitamente a sus datos (art. 8 de la Ley 1581 de 2012).</p>

    <h2>5. Consultas y reclamos</h2>
    <p>Se envían a {{ $p['contact_email'] }}. Las consultas se responden en máximo 10 días hábiles y los reclamos en máximo 15 días hábiles (arts. 14 y 15 de la Ley 1581 de 2012).</p>

    <h2>6. Seguridad y conservación</h2>
    <p>Acceso solo con usuario personal y permisos por rol; verificación en dos pasos para los roles administrativos; conexión cifrada (HTTPS); respaldos diarios cifrados; registro de auditoría de accesos, exportaciones y consultas del historial. Los registros de dietas se conservan el tiempo que exija la normativa de historia clínica y de facturación aplicable a la institución.</p>

    <h2>7. Deber de confidencialidad del personal</h2>
    <p>Quien usa NutriTrack se compromete a acceder solo a la información necesaria para sus funciones, no compartir su usuario ni su contraseña, no copiar ni divulgar datos de pacientes fuera de la institución, y reportar de inmediato cualquier incidente de seguridad.</p>
</div>
