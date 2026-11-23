<?php
/**
 * Shared config builder (create form + hub settings).
 * @var array<string,mixed> $cfg current values
 * @var array<string,string> $errors
 * @var bool|null $modoCompacto true inside hub sidebar settings
 */
use PingPong\Service\FiestaService;

$errors = $errors ?? [];
$compacto = (bool) ($modoCompacto ?? false);
$uid = 'fc_' . substr(md5(uniqid('', true)), 0, 6);

$modalidadCfg = (string) ($cfg['modalidad'] ?? 'DOBLES');
$formatoCfg = (string) ($cfg['formato'] ?? 'ELIMINACION');
$numGruposCfg = (int) ($cfg['num_grupos'] ?? 2);
$clasifCfg = (int) ($cfg['clasificados_por_grupo'] ?? 2);
$mejorDeCfg = (string) ($cfg['mejor_de'] ?? '1');

$overridesCfg = FiestaService::parsearSetsRondas(
    is_string($cfg['sets_rondas_json'] ?? null) ? $cfg['sets_rondas_json'] : null
);
$valElim = array_key_exists('mejor_de_elim', $cfg)
    ? (string) $cfg['mejor_de_elim']
    : (isset($overridesCfg['eliminatoria']) ? (string) $overridesCfg['eliminatoria'] : '');
$valSemis = array_key_exists('mejor_de_semis', $cfg)
    ? (string) $cfg['mejor_de_semis']
    : (isset($overridesCfg['semifinal']) ? (string) $overridesCfg['semifinal'] : '');
$valFinal = array_key_exists('mejor_de_final', $cfg)
    ? (string) $cfg['mejor_de_final']
    : (isset($overridesCfg['final']) ? (string) $overridesCfg['final'] : '');

$err = static fn (string $k): string => isset($errors[$k])
    ? '<p class="field__error">' . icon('alert') . ' ' . e($errors[$k]) . '</p>'
    : '';
?>
<div class="fiesta-cfg<?= $compacto ? ' fiesta-cfg--compact' : '' ?>" data-fiesta-config>

    <!-- Presets rápidos en 1 clic -->
    <div class="fiesta-cfg-block fiesta-cfg-block--first">
        <div class="fiesta-cfg-block__head">
            <span class="fiesta-cfg-step fiesta-cfg-step--accent"><?= icon('settings') ?></span>
            <div>
                <span class="fiesta-cfg-block__title">Plantillas rápidas</span>
                <p class="fiesta-cfg-block__sub">Arrancá con un formato listo o personalizá cada paso abajo.</p>
            </div>
        </div>
        <div class="fiesta-presets" role="group" aria-label="Plantillas rápidas de torneo">
            <button type="button" class="fiesta-preset" data-preset='{"formato":"ELIMINACION","mejor_de":"1","mejor_de_elim":"","mejor_de_semis":"","mejor_de_final":"3"}'>
                <span class="fiesta-preset__icon fiesta-preset__icon--blue"><?= icon('activity') ?></span>
                <span class="fiesta-preset__body">
                    <span class="fiesta-preset__name">Relámpago</span>
                    <span class="fiesta-preset__meta">Directa · 1 set · Final Bo3</span>
                </span>
            </button>
            <button type="button" class="fiesta-preset" data-preset='{"formato":"GRUPOS","num_grupos":"2","clasificados_por_grupo":"2","mejor_de":"1","mejor_de_elim":"","mejor_de_semis":"3","mejor_de_final":"5"}'>
                <span class="fiesta-preset__icon fiesta-preset__icon--purple"><?= icon('layers') ?></span>
                <span class="fiesta-preset__body">
                    <span class="fiesta-preset__name">Champions</span>
                    <span class="fiesta-preset__meta">2 grupos · Semis Bo3 · Final Bo5</span>
                </span>
            </button>
            <button type="button" class="fiesta-preset" data-preset='{"formato":"LIGA","num_grupos":"1","clasificados_por_grupo":"4","mejor_de":"1","mejor_de_elim":"","mejor_de_semis":"3","mejor_de_final":"5"}'>
                <span class="fiesta-preset__icon fiesta-preset__icon--green"><?= icon('table') ?></span>
                <span class="fiesta-preset__body">
                    <span class="fiesta-preset__name">Liga Épica</span>
                    <span class="fiesta-preset__meta">Todos vs todos · Top 4 · Final Bo5</span>
                </span>
            </button>
            <button type="button" class="fiesta-preset" data-preset='{"formato":"ELIMINACION","mejor_de":"3","mejor_de_elim":"","mejor_de_semis":"","mejor_de_final":"5"}'>
                <span class="fiesta-preset__icon fiesta-preset__icon--gold"><?= icon('trophy') ?></span>
                <span class="fiesta-preset__body">
                    <span class="fiesta-preset__name">Grand Slam</span>
                    <span class="fiesta-preset__meta">Directa Bo3 · Gran Final Bo5</span>
                </span>
            </button>
        </div>
    </div>

    <!-- Paso 1: Modalidad -->
    <div class="fiesta-cfg-block">
        <div class="fiesta-cfg-block__head">
            <span class="fiesta-cfg-step">1</span>
            <div>
                <span class="fiesta-cfg-block__title" id="<?= $uid ?>-l-mod">Modalidad</span>
                <p class="fiesta-cfg-block__sub">¿Se sortean parejas sorpresa o juega cada uno por su cuenta?</p>
            </div>
        </div>
        <div class="segmented-choice" role="radiogroup" aria-labelledby="<?= $uid ?>-l-mod">
            <label class="choice-card">
                <input type="radio" name="modalidad" value="DOBLES"<?= $modalidadCfg === 'DOBLES' ? ' checked' : '' ?> data-cfg-field="modalidad">
                <?= icon('users') ?>
                <span>
                    <span class="choice-card__title">Dobles</span>
                    <span class="choice-card__meta">Parejas armadas al azar en la ruleta</span>
                </span>
            </label>
            <label class="choice-card">
                <input type="radio" name="modalidad" value="INDIVIDUAL"<?= $modalidadCfg === 'INDIVIDUAL' ? ' checked' : '' ?> data-cfg-field="modalidad">
                <?= icon('user') ?>
                <span>
                    <span class="choice-card__title">Individual</span>
                    <span class="choice-card__meta">1 vs 1, cada jugador por su cuenta</span>
                </span>
            </label>
        </div>
        <?= $err('modalidad') ?>
    </div>

    <!-- Paso 2: Estructura / Formato -->
    <div class="fiesta-cfg-block">
        <div class="fiesta-cfg-block__head">
            <span class="fiesta-cfg-step">2</span>
            <div>
                <span class="fiesta-cfg-block__title" id="<?= $uid ?>-l-fmt">Estructura del torneo</span>
                <p class="fiesta-cfg-block__sub">Elegí si se define a eliminación directa, por grupos o en liga única.</p>
            </div>
        </div>
        <div class="segmented-choice segmented-choice--3" role="radiogroup" aria-labelledby="<?= $uid ?>-l-fmt">
            <label class="choice-card">
                <input type="radio" name="formato" value="ELIMINACION"<?= $formatoCfg === 'ELIMINACION' ? ' checked' : '' ?> data-formato data-cfg-field="formato">
                <?= icon('bracket') ?>
                <span>
                    <span class="choice-card__title">Eliminación directa</span>
                    <span class="choice-card__meta">Cuadro directo, el que pierde sale</span>
                </span>
            </label>
            <label class="choice-card">
                <input type="radio" name="formato" value="GRUPOS"<?= $formatoCfg === 'GRUPOS' ? ' checked' : '' ?> data-formato data-cfg-field="formato">
                <?= icon('layers') ?>
                <span>
                    <span class="choice-card__title">Grupos + eliminatoria</span>
                    <span class="choice-card__meta">Varios grupos y cruces entre mejores</span>
                </span>
            </label>
            <label class="choice-card">
                <input type="radio" name="formato" value="LIGA"<?= $formatoCfg === 'LIGA' ? ' checked' : '' ?> data-formato data-cfg-field="formato">
                <?= icon('table') ?>
                <span>
                    <span class="choice-card__title">Liga todos vs todos</span>
                    <span class="choice-card__meta">Tabla única con puntos + eliminatoria</span>
                </span>
            </label>
        </div>
        <?= $err('formato') ?>

        <div class="fiesta-grupos-panel" data-solo-grupos>
            <div class="fiesta-grupos-panel__field" data-solo-multi-grupo>
                <label class="fiesta-grupos-panel__label" for="<?= $uid ?>-grupos">Cantidad de grupos</label>
                <div class="fiesta-pill-picker" data-pill-for="<?= $uid ?>-grupos">
                    <?php foreach ([2, 3, 4] as $gOpt): ?>
                        <button type="button" class="fiesta-pick<?= $numGruposCfg === $gOpt ? ' is-active' : '' ?>" data-pick-val="<?= $gOpt ?>"><?= $gOpt ?> grupos</button>
                    <?php endforeach; ?>
                    <input type="number" id="<?= $uid ?>-grupos" name="num_grupos" min="1" max="16" inputmode="numeric"
                           class="fiesta-pick-input" value="<?= e((string) $numGruposCfg) ?>" data-cfg-field="num_grupos" aria-label="Cantidad de grupos">
                </div>
                <?= $err('num_grupos') ?>
            </div>

            <div class="fiesta-grupos-panel__field">
                <label class="fiesta-grupos-panel__label" for="<?= $uid ?>-clasif" data-clasificados-label>Clasifican por grupo</label>
                <div class="fiesta-pill-picker" data-pill-for="<?= $uid ?>-clasif">
                    <?php foreach ([1, 2, 4, 8] as $cOpt): ?>
                        <button type="button" class="fiesta-pick<?= $clasifCfg === $cOpt ? ' is-active' : '' ?>" data-pick-val="<?= $cOpt ?>" data-clasif-opt="<?= $cOpt ?>">Top <?= $cOpt ?></button>
                    <?php endforeach; ?>
                    <input type="number" id="<?= $uid ?>-clasif" name="clasificados_por_grupo" min="1" max="8" inputmode="numeric"
                           class="fiesta-pick-input" value="<?= e((string) $clasifCfg) ?>" data-clasificados-input data-cfg-field="clasificados_por_grupo" aria-label="Clasificados">
                </div>
                <p class="field__hint" data-liga-hint hidden>Los mejores de la tabla general pasan al cuadro eliminatorio sembrados (1.º vs último).</p>
                <?= $err('clasificados_por_grupo') ?>
            </div>
        </div>
    </div>

    <!-- Paso 3: Matriz visual de sets por etapa -->
    <div class="fiesta-cfg-block">
        <div class="fiesta-cfg-block__head">
            <span class="fiesta-cfg-step">3</span>
            <div>
                <span class="fiesta-cfg-block__title">Sets por etapa</span>
                <p class="fiesta-cfg-block__sub">Definí el ritmo base y subí la intensidad en las rondas decisivas.</p>
            </div>
        </div>

        <div class="fiesta-sets-matrix">
            <!-- Base (Grupos / Liga o general) -->
            <div class="fiesta-sets-row fiesta-sets-row--base">
                <div class="fiesta-sets-row__info">
                    <span class="fiesta-sets-row__icon"><?= icon('paddle') ?></span>
                    <div>
                        <strong class="fiesta-sets-row__name" data-base-sets-label>Fase base</strong>
                        <span class="fiesta-sets-row__desc" data-base-sets-desc>Ritmo por defecto del torneo</span>
                    </div>
                </div>
                <div class="fiesta-seg" role="radiogroup" aria-label="Sets base">
                    <?php foreach (['1' => '1 set', '3' => 'Bo3', '5' => 'Bo5'] as $v => $lbl): ?>
                        <label class="fiesta-seg__opt">
                            <input type="radio" name="mejor_de" value="<?= $v ?>"<?= $mejorDeCfg === $v ? ' checked' : '' ?> data-cfg-field="mejor_de">
                            <span><?= $lbl ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?= $err('mejor_de') ?>

            <!-- Eliminatoria (rondas previas) -->
            <div class="fiesta-sets-row" data-stage-elim>
                <div class="fiesta-sets-row__info">
                    <span class="fiesta-sets-row__icon"><?= icon('bracket') ?></span>
                    <div>
                        <strong class="fiesta-sets-row__name">Eliminatoria</strong>
                        <span class="fiesta-sets-row__desc">Octavos, cuartos y rondas previas</span>
                    </div>
                </div>
                <div class="fiesta-seg" role="radiogroup" aria-label="Sets en eliminatoria">
                    <?php foreach (['' => 'Igual', '1' => '1 set', '3' => 'Bo3', '5' => 'Bo5'] as $v => $lbl): ?>
                        <label class="fiesta-seg__opt">
                            <input type="radio" name="mejor_de_elim" value="<?= $v ?>"<?= $valElim === $v ? ' checked' : '' ?> data-cfg-field="mejor_de_elim">
                            <span><?= $lbl ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?= $err('mejor_de_elim') ?>

            <!-- Semifinales -->
            <div class="fiesta-sets-row">
                <div class="fiesta-sets-row__info">
                    <span class="fiesta-sets-row__icon"><?= icon('swords') ?></span>
                    <div>
                        <strong class="fiesta-sets-row__name">Semifinales</strong>
                        <span class="fiesta-sets-row__desc">Penúltima ronda del cuadro</span>
                    </div>
                </div>
                <div class="fiesta-seg" role="radiogroup" aria-label="Sets en semifinales">
                    <?php foreach (['' => 'Igual', '1' => '1 set', '3' => 'Bo3', '5' => 'Bo5'] as $v => $lbl): ?>
                        <label class="fiesta-seg__opt">
                            <input type="radio" name="mejor_de_semis" value="<?= $v ?>"<?= $valSemis === $v ? ' checked' : '' ?> data-cfg-field="mejor_de_semis">
                            <span><?= $lbl ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?= $err('mejor_de_semis') ?>

            <!-- Gran Final -->
            <div class="fiesta-sets-row fiesta-sets-row--final">
                <div class="fiesta-sets-row__info">
                    <span class="fiesta-sets-row__icon fiesta-sets-row__icon--gold"><?= icon('trophy') ?></span>
                    <div>
                        <strong class="fiesta-sets-row__name">Gran Final</strong>
                        <span class="fiesta-sets-row__desc">El partido por el título</span>
                    </div>
                </div>
                <div class="fiesta-seg" role="radiogroup" aria-label="Sets en la Gran Final">
                    <?php foreach (['' => 'Igual', '1' => '1 set', '3' => 'Bo3', '5' => 'Bo5'] as $v => $lbl): ?>
                        <label class="fiesta-seg__opt">
                            <input type="radio" name="mejor_de_final" value="<?= $v ?>"<?= $valFinal === $v ? ' checked' : '' ?> data-cfg-field="mejor_de_final">
                            <span><?= $lbl ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?= $err('mejor_de_final') ?>
        </div>
        <p class="field__hint fiesta-sets-legend"><strong>1 set</strong> = rápido · <strong>Bo3</strong> = mejor de 3 (gana 2) · <strong>Bo5</strong> = mejor de 5 (gana 3).</p>
    </div>
</div>

<script>
    (function () {
        var script = document.currentScript;
        var form = (script && script.closest('form')) || document;
        var cfg = form.querySelector('[data-fiesta-config]');
        if (!cfg) { return; }

        var bloqueGrupos = cfg.querySelector('[data-solo-grupos]');
        var multiGrupo = cfg.querySelector('[data-solo-multi-grupo]');
        var clasifLabel = cfg.querySelector('[data-clasificados-label]');
        var clasifInput = cfg.querySelector('[data-clasificados-input]');
        var ligaHint = cfg.querySelector('[data-liga-hint]');
        var baseLabel = cfg.querySelector('[data-base-sets-label]');
        var baseDesc = cfg.querySelector('[data-base-sets-desc]');
        var stageElim = cfg.querySelector('[data-stage-elim]');
        var top1Btn = cfg.querySelector('[data-clasif-opt="1"]');

        function radioVal(name) {
            var el = cfg.querySelector('input[name="' + name + '"]:checked');
            return el ? el.value : '';
        }

        function setRadio(name, val) {
            var target = cfg.querySelector('input[name="' + name + '"][value="' + val + '"]');
            if (target) { target.checked = true; }
        }

        function syncPillPickers() {
            cfg.querySelectorAll('[data-pill-for]').forEach(function (wrap) {
                var input = document.getElementById(wrap.getAttribute('data-pill-for'));
                if (!input) { return; }
                var val = String(input.value);
                wrap.querySelectorAll('[data-pick-val]').forEach(function (btn) {
                    btn.classList.toggle('is-active', btn.getAttribute('data-pick-val') === val);
                });
            });
        }

        cfg.querySelectorAll('[data-pill-for]').forEach(function (wrap) {
            var input = document.getElementById(wrap.getAttribute('data-pill-for'));
            if (!input) { return; }
            wrap.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-pick-val]');
                if (!btn) { return; }
                input.value = btn.getAttribute('data-pick-val');
                sync();
            });
            input.addEventListener('input', sync);
        });

        cfg.querySelectorAll('[data-preset]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var data = JSON.parse(btn.getAttribute('data-preset'));
                Object.keys(data).forEach(function (k) {
                    var radio = cfg.querySelector('input[type="radio"][name="' + k + '"]');
                    if (radio) {
                        setRadio(k, data[k]);
                    } else {
                        var inp = cfg.querySelector('input[name="' + k + '"]');
                        if (inp) { inp.value = data[k]; }
                    }
                });
                cfg.querySelectorAll('[data-preset]').forEach(function (b) { b.classList.remove('is-active'); });
                btn.classList.add('is-active');
                sync();
            });
        });

        function formatSets(v) {
            return v === '5' ? 'Bo5' : (v === '3' ? 'Bo3' : '1 set');
        }

        function updatePreview() {
            var preview = document.querySelector('[data-fiesta-preview]');
            if (!preview) { return; }

            var modalidad = radioVal('modalidad') || 'DOBLES';
            var formato = radioVal('formato') || 'ELIMINACION';
            var numGrupos = Math.max(1, parseInt((cfg.querySelector('[name="num_grupos"]') || {}).value || '2', 10));
            var clasif = Math.max(1, parseInt((clasifInput || {}).value || '2', 10));
            var base = radioVal('mejor_de') || '1';
            var elim = radioVal('mejor_de_elim') || base;
            var semis = radioVal('mejor_de_semis') || elim;
            var fin = radioVal('mejor_de_final') || elim;

            var nameInput = form.querySelector('#f-nombre');
            var titleEl = preview.querySelector('[data-pv-title]');
            if (titleEl) {
                var nm = nameInput ? nameInput.value.trim() : '';
                titleEl.textContent = nm || 'Tu próximo Torneo Fiesta';
            }

            // Build live visual timeline (SVG icons, zero system emojis)
            var steps = [];
            steps.push({ icon: 'shuffle', title: modalidad === 'DOBLES' ? 'Ruleta de parejas' : 'Ruleta de siembra', tag: modalidad === 'DOBLES' ? 'Dobles' : 'Individual' });
            if (formato === 'GRUPOS') {
                steps.push({ icon: 'layers', title: numGrupos + ' grupos · todos vs todos', tag: formatSets(base) });
                steps.push({ icon: 'bracket', title: 'Eliminatoria · Top ' + clasif + '/grupo', tag: formatSets(elim) });
            } else if (formato === 'LIGA') {
                steps.push({ icon: 'table', title: 'Liga todos contra todos', tag: formatSets(base) });
                steps.push({ icon: 'bracket', title: 'Eliminatoria · Top ' + clasif, tag: formatSets(elim) });
            } else {
                steps.push({ icon: 'bracket', title: 'Eliminatoria directa', tag: formatSets(base) });
            }
            if (semis !== elim) {
                steps.push({ icon: 'swords', title: 'Semifinales', tag: formatSets(semis) });
            }
            steps.push({ icon: 'trophy', title: 'Gran Final', tag: formatSets(fin), gold: true });

            var flowEl = preview.querySelector('[data-pv-flow]');
            if (flowEl) {
                flowEl.innerHTML = steps.map(function (s) {
                    return '<li class="fiesta-pv__step' + (s.gold ? ' is-gold' : '') + '">'
                        + '<span class="fiesta-pv__dot" aria-hidden="true"><svg class="icon"><use href="#i-' + s.icon + '"></use></svg></span>'
                        + '<span class="fiesta-pv__stitle">' + s.title + '</span>'
                        + '<span class="fiesta-pv__stag">' + s.tag + '</span>'
                        + '</li>';
                }).join('');
            }

            // Live player/match estimation from selector (if present)
            var sel = form.querySelector('[data-selector-jugadores] select[multiple]');
            var nJug = sel ? Array.prototype.filter.call(sel.options, function (o) { return o.selected; }).length : 0;
            var equipos = modalidad === 'DOBLES' ? Math.floor(nJug / 2) : nJug;
            var equiposEl = preview.querySelector('[data-pv-teams]');
            var matchesEl = preview.querySelector('[data-pv-matches]');
            var noteEl = preview.querySelector('[data-pv-note]');

            if (equiposEl) {
                if (nJug === 0) {
                    equiposEl.textContent = 'Sin anotados aún';
                } else if (modalidad === 'DOBLES') {
                    equiposEl.textContent = equipos + ' pareja' + (equipos === 1 ? '' : 's') + (nJug % 2 ? ' (falta 1)' : '');
                } else {
                    equiposEl.textContent = equipos + ' jugador' + (equipos === 1 ? '' : 'es');
                }
            }

            var totalPartidos = 0;
            if (equipos >= 2) {
                if (formato === 'ELIMINACION') {
                    totalPartidos = equipos - 1;
                } else if (formato === 'LIGA') {
                    var cl = Math.min(equipos, Math.max(2, clasif));
                    totalPartidos = (equipos * (equipos - 1)) / 2 + (cl - 1);
                } else {
                    var g = Math.max(1, numGrupos);
                    var baseG = Math.floor(equipos / g);
                    var extra = equipos % g;
                    var pGrupos = 0;
                    for (var i = 0; i < g; i++) {
                        var sz = baseG + (i < extra ? 1 : 0);
                        pGrupos += (sz * (sz - 1)) / 2;
                    }
                    var clTotal = Math.min(equipos, g * clasif);
                    totalPartidos = pGrupos + Math.max(0, clTotal - 1);
                }
            }

            if (matchesEl) {
                matchesEl.textContent = totalPartidos > 0 ? totalPartidos + ' partidos' : '—';
            }
            if (noteEl) {
                if (nJug === 0) {
                    noteEl.textContent = 'Podés crear la fiesta ahora y sumar jugadores después.';
                } else if (modalidad === 'DOBLES' && nJug % 2 !== 0) {
                    noteEl.textContent = 'En dobles necesitás un número par de jugadores para sortear.';
                } else if (totalPartidos <= 8) {
                    noteEl.textContent = 'Ritmo ágil — ideal para una tarde rápida.';
                } else if (totalPartidos <= 20) {
                    noteEl.textContent = 'Duración equilibrada — gran equilibrio entre juego y emoción.';
                } else {
                    noteEl.textContent = 'Torneo largo — buena idea jugar la fase previa a 1 set.';
                }
            }
        }

        function sync() {
            var val = radioVal('formato');
            var esGrupos = val === 'GRUPOS';
            var esLiga = val === 'LIGA';

            if (bloqueGrupos) { bloqueGrupos.hidden = !esGrupos && !esLiga; }
            if (multiGrupo) { multiGrupo.hidden = !esGrupos; }
            if (top1Btn) { top1Btn.hidden = esLiga; }

            if (clasifLabel) {
                clasifLabel.textContent = esLiga ? 'Clasifican a la eliminatoria' : 'Clasifican por grupo';
            }
            if (clasifInput) {
                clasifInput.min = esLiga ? '2' : '1';
                clasifInput.max = '8';
                if (esLiga && Number(clasifInput.value) < 2) {
                    clasifInput.value = '4';
                }
            }
            if (ligaHint) { ligaHint.hidden = !esLiga; }

            if (baseLabel && baseDesc) {
                if (esGrupos) {
                    baseLabel.textContent = 'Fase de grupos (base)';
                    baseDesc.textContent = 'Aplica a grupos y rondas sin ajuste';
                } else if (esLiga) {
                    baseLabel.textContent = 'Liga todos vs todos (base)';
                    baseDesc.textContent = 'Aplica a la liga y rondas sin ajuste';
                } else {
                    baseLabel.textContent = 'Rondas iniciales (base)';
                    baseDesc.textContent = 'Aplica a todo el cuadro salvo ajustes';
                }
            }
            if (stageElim) {
                stageElim.hidden = (!esGrupos && !esLiga);
            }

            syncPillPickers();
            updatePreview();
        }

        cfg.addEventListener('change', function (e) {
            if (e.target && e.target.hasAttribute('data-cfg-field')) {
                cfg.querySelectorAll('[data-preset]').forEach(function (b) { b.classList.remove('is-active'); });
                sync();
            }
        });

        var nameInput = form.querySelector('#f-nombre');
        if (nameInput) { nameInput.addEventListener('input', updatePreview); }

        var sel = form.querySelector('[data-selector-jugadores] select[multiple]');
        if (sel) { sel.addEventListener('change', updatePreview); }

        sync();
    })();
</script>
