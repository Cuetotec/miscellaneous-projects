<?php
defined('MOODLE_INTERNAL') || die();

class block_auth_pattern_block extends block_base {

    public function init() {
        $this->title = "Seguridad por Patrón";
    }

    public function get_content() {
        global $USER, $PAGE;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();

        // Lógica de guardado (Pattern y Tiempo)
        $nuevo_patron = optional_param('set_pattern', '', PARAM_ALPHANUM);
        if ($nuevo_patron) {
            set_user_preference('auth_pattern_code', $nuevo_patron, $USER->id);
            set_user_preference('auth_pattern_last_time', time(), $USER->id);
            redirect($PAGE->url);
        }

        $validar_ahora = optional_param('validar_acceso', 0, PARAM_INT);
        if ($validar_ahora) {
            set_user_preference('auth_pattern_last_time', time(), $USER->id);
            redirect($PAGE->url);
        }

        // Recuperar datos de Moodle
        $patron_db = get_user_preferences('auth_pattern_code', '', $USER->id);
        $ultimo_acceso_db = get_user_preferences('auth_pattern_last_time', 0, $USER->id);
        $ahora = time();

        // Se muestra si: No hay patrón O han pasado más de 24 horas (86400 seg)
        $mostrar_bloqueo = ($patron_db === '' || ($ahora - $ultimo_acceso_db) > 86400);

        // Si NO necesita mostrar bloqueo, el bloque devuelve vacío y no sale nada
        if (!$mostrar_bloqueo) {
            $this->content->text = '';
            return $this->content;
        }

        // Si llegamos aquí, inyectamos el JS de bloqueo
        ob_start();
        ?>
        <script>
        (function() {
            const PATRON_DB = "<?php echo $patron_db; ?>";
            let intento = "";

            function inyectarInterfaz() {
                if (document.getElementById("pattern-overlay")) return;

                const esRegistro = (PATRON_DB === "");
                const overlay = document.createElement("div");
                overlay.id = "pattern-overlay";
                overlay.style = "position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(10,12,15,0.99); z-index:9999999; display:flex; align-items:center; justify-content:center; color:white; font-family:sans-serif;"; 
                
                overlay.innerHTML = `
                    <div style="background:#1a1a1a; padding:40px; border-radius:20px; text-align:center; border:1px solid #333; width:300px;">
                        <h3 style="color:#00d1b2; margin-top:0;">${esRegistro ? "Configurar Acceso" : "Acceso Protegido"}</h3>
                        <p style="font-size:12px; color:#888; margin-bottom:20px;">${esRegistro ? "Crea un patrón de seguridad" : "Dibuja tu patrón para entrar"}</p>
                        <div id="pattern-grid" style="display:grid; grid-template-columns: repeat(3, 60px); gap: 15px; margin: 0 auto 25px auto; width:210px;">
                            ${[1,2,3,4,5,6,7,8,9].map(i => `<div class="d-dot" data-id="${i}" style="width:50px; height:50px; background:#fff; border-radius:50%; cursor:pointer; border:2px solid #444;"></div>`).join("")}
                        </div>
                        <button id="btn-final-action" style="width:100%; padding:12px; background:#00d1b2; color:white; border:none; border-radius:8px; cursor:pointer; font-weight:bold;">${esRegistro ? "GUARDAR" : "DESBLOQUEAR"}</button>
                        <p id="err-msg" style="color:#ff3860; margin-top:15px; font-weight:bold; height:15px;"></p>
                    </div>`;

                document.body.prepend(overlay);

                const dots = overlay.querySelectorAll(".d-dot");
                dots.forEach(d => {
                    d.onclick = function() {
                        if (!intento.includes(this.dataset.id)) {
                            this.style.background = "#00d1b2";
                            intento += this.dataset.id;
                        }
                    };
                });

                document.getElementById("btn-final-action").onclick = function() {
                    const url = new URL(window.location.href);
                    if (esRegistro) {
                        if (intento.length < 3) { alert("Mínimo 3 puntos"); return; }
                        url.searchParams.set("set_pattern", intento);
                        window.location.href = url.toString();
                    } else {
                        if (intento === PATRON_DB) {
                            url.searchParams.set("validar_acceso", "1");
                            window.location.href = url.toString();
                        } else {
                            document.getElementById("err-msg").innerText = "Patrón Incorrecto";
                            intento = "";
                            dots.forEach(dot => dot.style.background = "#fff");
                        }
                    }
                };
            }

            inyectarInterfaz();
        })();
        </script>
        <?php
        $this->content->text = ob_get_clean();
        return $this->content;
    }
}