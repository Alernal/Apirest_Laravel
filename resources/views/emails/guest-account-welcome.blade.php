<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta charset="utf-8">
  <title>Bienvenido a NURAE</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="x-ua-compatible" content="ie=edge">
  <!--[if mso]>
    <style type="text/css">
      body, table, td, a { font-family: Arial, Helvetica, sans-serif !important; }
    </style>
  <![endif]-->
  <style>
    /* Resets básicos */
    html, body { margin:0 !important; padding:0 !important; height:100% !important; width:100% !important; }
    * { -ms-text-size-adjust:100%; -webkit-text-size-adjust:100%; }
    table, td { mso-table-lspace:0pt !important; mso-table-rspace:0pt !important; }
    img { -ms-interpolation-mode:bicubic; border:0; outline:none; text-decoration:none; }
    a { text-decoration:none; }
    /* Responsive */
    @media screen and (max-width: 600px) {
      .container { width:100% !important; }
      .px-24 { padding-left:16px !important; padding-right:16px !important; }
      .py-24 { padding-top:16px !important; padding-bottom:16px !important; }
      .text-center-sm { text-align:center !important; }
      .stack { display:block !important; width:100% !important; }
    }
    /* Modo oscuro (soporte parcial) */
    @media (prefers-color-scheme: dark) {
      body, .bg-body { background-color:#0f1115 !important; }
      .card { background-color:#161a20 !important; }
      .text { color:#e7e9ee !important; }
      .muted { color:#b9bfcc !important; }
      .btn { background-color:#22c55e !important; color:#0f1115 !important; }
    }
  </style>
</head>
<body class="bg-body" style="margin:0; padding:0; background-color:#f3f4f6;">
  <!-- Preheader (texto de vista previa) -->
  <div style="display:none; max-height:0; overflow:hidden; mso-hide:all; font-size:1px; line-height:1px; color:#f3f4f6;">
    Tu cuenta en NURAE ha sido creada. Aquí tienes tu acceso y próximos pasos.
  </div>

  <center role="article" aria-roledescription="email" lang="es" style="width:100%; background-color:#f3f4f6;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
      <tr>
        <td align="center" style="padding: 24px;">
          <!-- Contenedor -->
          <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" class="container" style="width:600px; max-width:100%;">
            <!-- Header / Logo -->
            <tr>
              <td align="left" class="px-24" style="padding:24px 24px 0 24px;">
                <a href="{{ url('https://nurae.com.co') }}" target="_blank">
                  <img src="{{ asset('logo-nurae.png') }}" width="120" height="auto" alt="NURAE" style="display:block; border:0;">
                </a>
              </td>
            </tr>

            <!-- Tarjeta -->
            <tr>
              <td class="px-24 py-24" style="padding:24px;">

                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" class="card" style="background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 1px 2px rgba(16,24,40,.06);">
                  <tr>
                    <td class="px-24 py-24 text" style="padding:32px; font-family:Arial, Helvetica, sans-serif; color:#0f172a;">

                      <h1 style="margin:0 0 12px 0; font-size:24px; line-height:32px; font-weight:700;">
                        ¡Hola {{ $user->name ?? 'cliente' }}!
                      </h1>

                      <p style="margin:0 0 16px 0; font-size:16px; line-height:24px;">
                        ¡Bienvenido/a a <strong>NURAE</strong>! Tu cuenta ya está lista para que puedas
                        seguir tus pedidos, guardar direcciones y revisar tu historial de compras de forma segura.
                      </p>

                      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin:16px 0 8px 0;">
                        <tr>
                          <td style="padding:16px; background:#f8fafc; border:1px solid #e5e7eb; border-radius:8px;">
                            <p style="margin:0 0 8px 0; font-size:14px; line-height:20px; color:#334155;"><strong>Tus datos de acceso</strong></p>
                            <p style="margin:0; font-size:14px; line-height:20px; color:#334155;">
                              <strong>Email:</strong> {{ $user->email }}<br>
                              <strong>Contraseña temporal:</strong> {{ $password }}
                            </p>
                          </td>
                        </tr>
                      </table>

                      <p style="margin:16px 0 24px 0; font-size:14px; line-height:22px; color:#475569;">
                        Por tu seguridad, te pedimos iniciar sesión y cambiar la contraseña temporal por una nueva.
                      </p>

                      <!-- Botón (bulletproof) -->
                      <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="left" class="stack" style="margin:0 0 16px 0;">
                        <tr>
                          <td align="center" bgcolor="#16a34a" class="btn" style="border-radius:8px;">
                            <!--[if mso]>
                              <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word"
                                href="{{ url('https://nurae.com.co/login') }}" style="height:44px;v-text-anchor:middle;width:220px;" arcsize="12%" stroke="f" fillcolor="#16a34a">
                                <w:anchorlock/>
                                <center style="color:#ffffff;font-family:Arial, Helvetica, sans-serif;font-size:16px;font-weight:bold;">
                                  Iniciar sesión
                                </center>
                              </v:roundrect>
                            <![endif]-->
                            <!--[if !mso]><!-- -->
                            <a href="{{ url('https://nurae.com.co/login') }}" target="_blank"
                               style="display:inline-block; padding:12px 20px; font-family:Arial, Helvetica, sans-serif; font-size:16px; font-weight:700; color:#ffffff; background:#16a34a; border-radius:8px;">
                              Iniciar sesión
                            </a>
                            <!--<![endif]-->
                          </td>
                        </tr>
                      </table>

                      <p class="muted" style="margin:0 0 24px 0; font-size:12px; line-height:18px; color:#64748b;">
                        Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                        <span style="word-break:break-all;">{{ url('https://nurae.com.co/login') }}</span>
                      </p>

                      <hr style="border:none; border-top:1px solid #e5e7eb; margin:24px 0;">

                      <p style="margin:0 0 12px 0; font-size:14px; line-height:22px;">
                        ¿Necesitas ayuda? Responde a este correo o visita nuestra página de
                        <a href="{{ url('/contacto') }}" target="_blank" style="color:#16a34a; font-weight:600;">Contacto</a>.
                      </p>

                      <p class="muted" style="margin:0; font-size:12px; line-height:18px; color:#64748b;">
                        Si no solicitaste esta cuenta, ignora este mensaje o escríbenos para ayudarte a asegurarla.
                      </p>

                    </td>
                  </tr>
                </table>

              </td>
            </tr>

            <!-- Footer -->
            <tr>
              <td class="px-24" style="padding:0 24px 32px 24px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                  <tr>
                    <td class="text-center-sm" style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#94a3b8; text-align:left;">
                      © {{ date('Y') }} NURAE. Todos los derechos reservados.
                    </td>
                    <td align="right" class="text-center-sm" style="text-align:right;">
                      <a href="{{ url('https://nurae.com.co') }}" target="_blank" style="font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:#94a3b8;">
                        nurae.com.co
                      </a>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>

          </table>
          <!-- /Contenedor -->
        </td>
      </tr>
    </table>
  </center>
</body>
</html>
