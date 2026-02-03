<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';

require_teacher();

$tid = (int) teacher_id();
$page_title = 'Dashboard • ' . APP_NAME;

function dash_bind_params($stmt, $types, &$params)
{
    $bind = [];
    $bind[] = &$types;
    for ($i = 0; $i < count($params); $i++) $bind[] = &$params[$i];
    return call_user_func_array('mysqli_stmt_bind_param', $bind);
}

function dash_scalar($sql, $types, $params)
{
    $conn = db();
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return null;

    if ($types !== '') dash_bind_params($stmt, $types, $params);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return null;
    }

    $val = null;

    if (function_exists('mysqli_stmt_get_result')) {
        $res = mysqli_stmt_get_result($stmt);
        if ($res) {
            $row = mysqli_fetch_row($res);
            if (is_array($row) && isset($row[0])) $val = $row[0];
        }
    } else {
        mysqli_stmt_bind_result($stmt, $out);
        if (mysqli_stmt_fetch($stmt)) $val = $out;
    }

    mysqli_stmt_close($stmt);
    return $val;
}

function dash_rows($sql, $types, $params)
{
    $conn = db();
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return [];

    if ($types !== '') dash_bind_params($stmt, $types, $params);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return [];
    }

    $rows = [];

    if (function_exists('mysqli_stmt_get_result')) {
        $res = mysqli_stmt_get_result($stmt);
        if ($res) while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    } else {
        $meta = mysqli_stmt_result_metadata($stmt);
        if ($meta) {
            $fields = mysqli_fetch_fields($meta);
            $row = [];
            $refs = [];

            for ($i = 0; $i < count($fields); $i++) {
                $name = $fields[$i]->name;
                $row[$name] = null;
                $refs[] = &$row[$name];
            }

            call_user_func_array('mysqli_stmt_bind_result', array_merge([$stmt], $refs));

            while (mysqli_stmt_fetch($stmt)) {
                $copy = [];
                foreach ($row as $k => $v) $copy[$k] = $v;
                $rows[] = $copy;
            }
        }
    }

    mysqli_stmt_close($stmt);
    return $rows;
}

function dash_fmt_date($raw)
{
    $ts = strtotime((string) $raw);
    if (!$ts) return (string) $raw;
    return date('M d, Y', $ts);
}

function dash_subject_emoji($subject)
{
    $s = strtolower(trim((string) $subject));
    if (strpos($s, 'sci') !== false) return '🧪';
    if (strpos($s, 'math') !== false) return '🧮';
    if (strpos($s, 'eng') !== false) return '📘';
    if (strpos($s, 'fil') !== false) return '🗣️';
    if (strpos($s, 'ict') !== false || strpos($s, 'comp') !== false) return '💻';
    if (strpos($s, 'ara') !== false) return '🌏';
    return '💡';
}

$teacherFirst = 'Teacher';
$teacherLast  = '';

$tr = dash_rows("SELECT first_name, last_name FROM users WHERE id = ? LIMIT 1", "i", [$tid]);
if (count($tr) === 1) {
    if (isset($tr[0]['first_name'])) $teacherFirst = (string) $tr[0]['first_name'];
    if (isset($tr[0]['last_name'])) $teacherLast  = (string) $tr[0]['last_name'];
}

$displayName = trim($teacherFirst . ' ' . $teacherLast);
if ($displayName === '') $displayName = 'Teacher';

$initials = 'T';
$a = trim((string) $teacherFirst);
$b = trim((string) $teacherLast);
$ia = $a !== '' ? mb_substr($a, 0, 1) : '';
$ib = $b !== '' ? mb_substr($b, 0, 1) : '';
$pair = strtoupper($ia . $ib);
if ($pair !== '') $initials = $pair;

$welcomeLast = trim((string) $teacherLast);
if ($welcomeLast === '') $welcomeLast = $displayName;

$totalQuizzes = dash_scalar("SELECT COUNT(*) FROM quizzes WHERE teacher_id = ?", "i", [$tid]);
if ($totalQuizzes === null) $totalQuizzes = 0;

$publishedQuizzes = dash_scalar("SELECT COUNT(*) FROM quizzes WHERE teacher_id = ? AND status = 'published'", "i", [$tid]);
if ($publishedQuizzes === null) $publishedQuizzes = 0;

$totalSubmissions = dash_scalar(
    "SELECT COUNT(*)
     FROM attempts a
     INNER JOIN quizzes q ON q.id = a.quiz_id
     WHERE q.teacher_id = ? AND a.submitted = 1",
    "i",
    [$tid]
);
if ($totalSubmissions === null) $totalSubmissions = 0;

$activeStudents = dash_scalar(
    "SELECT COUNT(DISTINCT a.student_id)
     FROM attempts a
     INNER JOIN quizzes q ON q.id = a.quiz_id
     WHERE q.teacher_id = ?",
    "i",
    [$tid]
);
if ($activeStudents === null) $activeStudents = 0;

$recentQuizzes = dash_rows(
    "SELECT id, title, subject, total_questions, total_points, quiz_code, status, created_at, updated_at
     FROM quizzes
     WHERE teacher_id = ?
     ORDER BY updated_at DESC, created_at DESC, id DESC
     LIMIT 6",
    "i",
    [$tid]
);

$days = 14;
$labels = [];
$values = [];
$map = [];

$today = new DateTime('today');
$start = new DateTime('today');
$start->modify('-' . ($days - 1) . ' days');

for ($i = 0; $i < $days; $i++) {
    $d = clone $start;
    $d->modify('+' . $i . ' days');
    $key = $d->format('Y-m-d');
    $labels[] = $d->format('M j');
    $values[] = 0;
    $map[$key] = $i;
}

$trend = dash_rows(
    "SELECT DATE(COALESCE(a.submitted_at, a.created_at)) AS d, COUNT(*) AS c
     FROM attempts a
     INNER JOIN quizzes q ON q.id = a.quiz_id
     WHERE q.teacher_id = ?
       AND a.submitted = 1
       AND DATE(COALESCE(a.submitted_at, a.created_at)) BETWEEN ? AND ?
     GROUP BY DATE(COALESCE(a.submitted_at, a.created_at))
     ORDER BY d ASC",
    "iss",
    [$tid, $start->format('Y-m-d'), $today->format('Y-m-d')]
);

for ($i = 0; $i < count($trend); $i++) {
    $k = isset($trend[$i]['d']) ? (string) $trend[$i]['d'] : '';
    if ($k !== '' && isset($map[$k])) {
        $idx = (int) $map[$k];
        $values[$idx] = isset($trend[$i]['c']) ? (int) $trend[$i]['c'] : 0;
    }
}

$avgRows = dash_rows(
    "SELECT q.id, q.title, q.total_points, AVG(a.total_score) AS avg_score, COUNT(a.id) AS submissions
     FROM quizzes q
     LEFT JOIN attempts a ON a.quiz_id = q.id AND a.submitted = 1
     WHERE q.teacher_id = ?
     GROUP BY q.id
     ORDER BY q.updated_at DESC, q.created_at DESC, q.id DESC
     LIMIT 6",
    "i",
    [$tid]
);

$avgLabels = [];
$avgValues = [];
$avgMeta = [];

for ($i = 0; $i < count($avgRows); $i++) {
    $t = isset($avgRows[$i]['title']) ? (string) $avgRows[$i]['title'] : 'Untitled';
    $tp = isset($avgRows[$i]['total_points']) ? (float) $avgRows[$i]['total_points'] : 0.0;
    $as = (isset($avgRows[$i]['avg_score']) && $avgRows[$i]['avg_score'] !== null) ? (float) $avgRows[$i]['avg_score'] : 0.0;
    $sc = isset($avgRows[$i]['submissions']) ? (int) $avgRows[$i]['submissions'] : 0;

    $pct = 0.0;
    if ($tp > 0) {
        $pct = ($as / $tp) * 100.0;
        if ($pct < 0) $pct = 0.0;
        if ($pct > 100) $pct = 100.0;
    }

    $avgLabels[] = $t;
    $avgValues[] = $pct;
    $avgMeta[] = [
        'avg_score' => round($as, 2),
        'total_points' => round($tp, 2),
        'submissions' => $sc
    ];
}

$payload = [
    'trend' => ['labels' => $labels, 'values' => $values],
    'avg' => ['labels' => $avgLabels, 'values' => $avgValues, 'meta' => $avgMeta]
];

$payload_json = json_encode($payload);

$toastSuccess = flash_get('success');
$toastError   = flash_get('error');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo e($page_title); ?></title>
  <link rel="stylesheet" href="./../assets/css/app.css">
  <link rel="stylesheet" href="./../assets/css/dashboard.css">
  <link rel="icon" href="./../assets/img/remove_logo.png" type="image/png">
</head>
<body class="dash-body">

<header class="dash-top">
  <div class="dash-wrap dash-top__inner">
    <a class="dash-brand" href="./../public/index.php">
      <img class="dash-brand__logo" src="./../assets/img/remove_logo.png" alt="Quizora">
      <span class="dash-brand__text">Quizora</span>
    </a>

    <nav class="dash-nav" aria-label="Primary">
      <a class="dash-nav__link is-active" href="./../public/teacher_dashboard.php">Dashboard</a>
      <a class="dash-nav__link" href="./../public/quiz_create.php">Quizzes</a>
      <a class="dash-nav__link" href="./../public/teacher_results.php">Results</a>
      <a class="dash-nav__link" href="./../public/teacher_students.php">Students</a>
    </nav>

    <div class="dash-right">
      <div class="dash-user" title="<?php echo e($displayName); ?>">
        <div class="dash-user__pic"><?php echo e($initials); ?></div>
      </div>

      <a class="dash-logout dash-logout--desk" href="./../public/logout.php">Logout</a>

      <button class="dash-burger" type="button" aria-controls="dashDrawer" aria-expanded="false" aria-label="Open menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</header>

<div class="dash-drawer" id="dashDrawer" hidden>
  <div class="dash-drawer__panel" role="dialog" aria-modal="true" aria-label="Menu">
    <div class="dash-drawer__top">
      <div class="dash-drawer__profile">
        <div class="dash-drawer__avatar"><?php echo e($initials); ?></div>
        <div class="dash-drawer__who">
          <div class="dash-drawer__name"><?php echo e($displayName); ?></div>
          <div class="dash-drawer__role">Teacher</div>
        </div>
      </div>

      <button class="dash-drawer__close" type="button" aria-label="Close menu">×</button>
    </div>

    <nav class="dash-drawer__nav" aria-label="Mobile">
      <a class="dash-drawer__link is-active" href="./../public/teacher_dashboard.php">Dashboard</a>
      <a class="dash-drawer__link" href="./../public/quiz_create.php">Quizzes</a>
      <a class="dash-drawer__link" href="./../public/teacher_results.php">Results</a>
      <a class="dash-drawer__link" href="./../public/teacher_students.php">Students</a>
    </nav>

    <div class="dash-drawer__bottom">
      <a class="dash-drawer__logout" href="./../public/logout.php">Logout</a>
    </div>
  </div>
</div>

<main class="dash-wrap dash-main">
  <?php if (is_string($toastSuccess) && $toastSuccess !== '') { ?>
    <div class="dash-toast dash-toast--ok" data-toast><?php echo e($toastSuccess); ?></div>
  <?php } ?>
  <?php if (is_string($toastError) && $toastError !== '') { ?>
    <div class="dash-toast dash-toast--bad" data-toast><?php echo e($toastError); ?></div>
  <?php } ?>

  <section class="dash-hero">
    <h1 class="dash-hero__title">Welcome back, Teacher <?php echo e($welcomeLast); ?>!</h1>
    <p class="dash-hero__sub">Here is what’s happening in your class today:</p>
  </section>

  <section class="dash-kpis" aria-label="Key performance indicators">
    <div class="dash-kpi">
      <div class="dash-kpi__ic is-primary" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M4 4h16v16H4z"/><path d="M7 14h2v3H7zM11 10h2v7h-2zM15 7h2v10h-2z"/></svg>
      </div>
      <div class="dash-kpi__body">
        <div class="dash-kpi__label">Total Quizzes</div>
        <div class="dash-kpi__value"><?php echo e((string) (int) $totalQuizzes); ?></div>
      </div>
    </div>

    <div class="dash-kpi">
      <div class="dash-kpi__ic is-success" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M9 16.2l-3.5-3.5L4 14.2 9 19l12-12-1.5-1.5z"/></svg>
      </div>
      <div class="dash-kpi__body">
        <div class="dash-kpi__label">Published Quizzes</div>
        <div class="dash-kpi__value"><?php echo e((string) (int) $publishedQuizzes); ?></div>
      </div>
    </div>

    <div class="dash-kpi">
      <div class="dash-kpi__ic is-secondary" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M6 2h9l3 3v17H6z"/><path d="M9 9h6v2H9zM9 13h6v2H9z"/></svg>
      </div>
      <div class="dash-kpi__body">
        <div class="dash-kpi__label">Total Submissions</div>
        <div class="dash-kpi__value"><?php echo e((string) (int) $totalSubmissions); ?></div>
      </div>
    </div>

    <div class="dash-kpi">
      <div class="dash-kpi__ic is-muted" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M16 11c1.7 0 3-1.3 3-3s-1.3-3-3-3-3 1.3-3 3 1.3 3 3 3zM8 11c1.7 0 3-1.3 3-3S9.7 5 8 5 5 6.3 5 8s1.3 3 3 3z"/><path d="M16 13c-2.3 0-7 1.2-7 3.5V20h14v-3.5c0-2.3-4.7-3.5-7-3.5zM8 13c-.3 0-.7 0-1.1.1C4.6 13.5 2 14.7 2 16.5V20h6v-3.5c0-1.2.6-2.2 1.6-3-.5-.3-1-.5-1.6-.5z"/></svg>
      </div>
      <div class="dash-kpi__body">
        <div class="dash-kpi__label">Active Students</div>
        <div class="dash-kpi__value"><?php echo e((string) (int) $activeStudents); ?></div>
      </div>
    </div>
  </section>

  <section class="dash-grid">
    <div class="dash-card dash-card--wide">
      <div class="dash-card__head">
        <div>
          <h2 class="dash-card__title">Recent Quizzes</h2>
          <div class="dash-card__hint">Latest quizzes you’ve created</div>
        </div>

        <div class="dash-actions">
          <a class="dash-btn dash-btn--primary" href="./../public/quiz_create.php">Create Quiz</a>
        </div>
      </div>

      <div class="dash-actionsbar">
        <div class="dash-search">
          <span class="dash-search__ic">🔎</span>
          <input id="quizSearch" class="dash-search__in" type="text" placeholder="Search quizzes..." autocomplete="off">
        </div>
      </div>

      <div class="dash-list" id="quizList">
        <?php if (count($recentQuizzes) === 0) { ?>
          <div class="dash-empty">
            <div class="dash-empty__t">No quizzes yet</div>
            <div class="dash-empty__s">Create your first quiz to see activity here.</div>
          </div>
        <?php } else { ?>
          <?php for ($i = 0; $i < count($recentQuizzes); $i++) { ?>
            <?php
              $q = $recentQuizzes[$i];

              $qid = isset($q['id']) ? (int) $q['id'] : 0;
              $title = isset($q['title']) ? (string) $q['title'] : 'Untitled';
              $subject = isset($q['subject']) ? (string) $q['subject'] : '';
              $status = isset($q['status']) ? (string) $q['status'] : 'draft';
              $tq = isset($q['total_questions']) ? (int) $q['total_questions'] : 0;

              $rawDate = '';
              if (isset($q['updated_at']) && (string) $q['updated_at'] !== '') $rawDate = (string) $q['updated_at'];
              else if (isset($q['created_at'])) $rawDate = (string) $q['created_at'];

              $dateTxt = dash_fmt_date($rawDate);
              $needle = strtolower($title . ' ' . $subject);
            ?>
            <div class="dash-item" data-title="<?php echo e($needle); ?>">
              <div class="dash-item__left">
                <div class="dash-item__badge" aria-hidden="true"><?php echo e(dash_subject_emoji($subject)); ?></div>
                <div class="dash-item__meta">
                  <div class="dash-item__title"><?php echo e($title); ?></div>
                  <div class="dash-item__sub">
                    <span><?php echo e($subject); ?></span>
                    <?php if ($dateTxt !== '') { ?>
                      <span class="dash-dot">•</span>
                      <span><?php echo e($dateTxt); ?></span>
                    <?php } ?>
                  </div>
                </div>
              </div>

              <div class="dash-item__mid">
                <div class="dash-mini">
                  <div class="dash-mini__num"><?php echo e((string) $tq); ?></div>
                  <div class="dash-mini__label">Questions</div>
                </div>
                <div class="dash-status dash-status--<?php echo e($status); ?>"><?php echo e(ucfirst($status)); ?></div>
              </div>

              <div class="dash-item__right">
                <a class="dash-btn dash-btn--secondary" href="./../public/quiz_builder.php?quiz_id=<?php echo e((string) $qid); ?>">Manage</a>
              </div>
            </div>
          <?php } ?>
        <?php } ?>
      </div>
    </div>

    <aside class="dash-side">
      <div class="dash-card">
        <div class="dash-card__head dash-card__head--tight">
          <div>
            <h2 class="dash-card__title">Submissions Trend</h2>
            <div class="dash-card__hint">Last 14 days</div>
          </div>
        </div>
        <div class="dash-chart" id="chartTrend" data-payload="<?php echo e($payload_json); ?>"></div>
      </div>

      <div class="dash-card">
        <div class="dash-card__head dash-card__head--tight">
          <div>
            <h2 class="dash-card__title">Avg Score by Quiz</h2>
            <div class="dash-card__hint">Percent of total points</div>
          </div>
        </div>
        <div class="dash-chart" id="chartAvg"></div>
        <div class="dash-legend" id="avgLegend"></div>
      </div>
    </aside>
  </section>
</main>

<footer class="dash-footer">
  <div class="dash-wrap dash-footer__inner">© <?php echo e((string) date('Y')); ?> <?php echo e(APP_NAME); ?></div>
</footer>

<script>
(function () {
  function qs(sel, root) { return (root || document).querySelector(sel); }
  function qsa(sel, root) { return (root || document).querySelectorAll(sel); }

  function allZero(arr) {
    if (!arr || !arr.length) return true;
    for (var i = 0; i < arr.length; i++) if ((arr[i] || 0) > 0) return false;
    return true;
  }

  function svgEl(tag) { return document.createElementNS('http://www.w3.org/2000/svg', tag); }
  function clamp(n, a, b) { return Math.max(a, Math.min(b, n)); }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function lockScroll(lock) { document.body.style.overflow = lock ? 'hidden' : ''; }

  function setupToasts() {
    var toasts = qsa('[data-toast]');
    for (var i = 0; i < toasts.length; i++) {
      (function (el) { setTimeout(function () { el.classList.add('hide'); }, 3600); })(toasts[i]);
    }
  }

  function setupSearch() {
    var input = qs('#quizSearch');
    var list = qs('#quizList');
    if (!input || !list) return;

    input.addEventListener('input', function () {
      var q = (input.value || '').toLowerCase();
      var items = qsa('.dash-item', list);
      for (var j = 0; j < items.length; j++) {
        var t = (items[j].getAttribute('data-title') || '').toLowerCase();
        items[j].style.display = (q === '' || t.indexOf(q) !== -1) ? '' : 'none';
      }
    });
  }

  function setupDrawer() {
    var burger = qs('.dash-burger');
    var drawer = qs('#dashDrawer');
    if (!burger || !drawer) return;

    var panel = qs('.dash-drawer__panel', drawer);
    var closeBtn = qs('.dash-drawer__close', drawer);

    function focusables() {
      return qsa('button, a, input, [tabindex]:not([tabindex="-1"])', drawer);
    }

    function openDrawer() {
      burger.setAttribute('aria-expanded', 'true');
      drawer.removeAttribute('hidden');
      drawer.classList.add('is-open');
      lockScroll(true);
      setTimeout(function () { if (closeBtn) closeBtn.focus(); }, 0);
    }

    function closeDrawer() {
      burger.setAttribute('aria-expanded', 'false');
      drawer.classList.remove('is-open');
      lockScroll(false);
      setTimeout(function () { drawer.setAttribute('hidden', 'hidden'); burger.focus(); }, 230);
    }

    function toggleDrawer() {
      var isOpen = burger.getAttribute('aria-expanded') === 'true' && !drawer.hasAttribute('hidden');
      if (isOpen) closeDrawer(); else openDrawer();
    }

    burger.addEventListener('click', function () { toggleDrawer(); });

    if (closeBtn) closeBtn.addEventListener('click', function () { closeDrawer(); });

    drawer.addEventListener('click', function (e) { if (e.target === drawer) closeDrawer(); });

    document.addEventListener('keydown', function (e) {
      if (drawer.hasAttribute('hidden')) return;

      if (e.key === 'Escape') { e.preventDefault(); closeDrawer(); return; }

      if (e.key === 'Tab') {
        var f = focusables();
        if (!f || !f.length) return;
        var first = f[0], last = f[f.length - 1];

        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    });

    var links = qsa('a', drawer);
    for (var k = 0; k < links.length; k++) links[k].addEventListener('click', function () { closeDrawer(); });

    window.addEventListener('resize', function () {
      if (window.innerWidth >= 981 && !drawer.hasAttribute('hidden')) closeDrawer();
    }, { passive: true });

    if (panel) {
      panel.addEventListener('transitionend', function () {
        if (!drawer.classList.contains('is-open') && !drawer.hasAttribute('hidden')) drawer.setAttribute('hidden', 'hidden');
      });
    }
  }

  function renderLine(target, labels, values) {
    var w = Math.max(320, Math.floor(target.getBoundingClientRect().width || target.clientWidth || 640));
    var h = 200;

    var padL = 36, padR = 12, padT = 16, padB = 30;
    var innerW = Math.max(10, w - padL - padR);
    var innerH = Math.max(10, h - padT - padB);

    var svg = svgEl('svg');
    svg.setAttribute('viewBox', '0 0 ' + w + ' ' + h);
    svg.setAttribute('width', '100%');
    svg.setAttribute('height', h);

    for (var g = 0; g <= 4; g++) {
      var y = padT + (innerH * g / 4);
      var ln = svgEl('line');
      ln.setAttribute('x1', padL);
      ln.setAttribute('x2', padL + innerW);
      ln.setAttribute('y1', y);
      ln.setAttribute('y2', y);
      ln.setAttribute('class', 'dash-gridline');
      svg.appendChild(ln);
    }

    if (!values || values.length === 0 || allZero(values)) {
      var msg = svgEl('text');
      msg.setAttribute('x', padL + innerW / 2);
      msg.setAttribute('y', padT + innerH / 2);
      msg.setAttribute('text-anchor', 'middle');
      msg.setAttribute('class', 'dash-axis');
      msg.textContent = 'No submissions yet';
      svg.appendChild(msg);

      target.innerHTML = '';
      target.appendChild(svg);
      return;
    }

    var maxV = 0;
    for (var i = 0; i < values.length; i++) maxV = Math.max(maxV, values[i] || 0);
    if (maxV < 1) maxV = 1;

    var d = '';
    var a = '';

    for (var j = 0; j < values.length; j++) {
      var x = padL + (innerW * (values.length === 1 ? 0 : j / (values.length - 1)));
      var yv = padT + innerH - (innerH * ((values[j] || 0) / maxV));
      if (j === 0) { d += 'M ' + x + ' ' + yv; a += 'M ' + x + ' ' + (padT + innerH) + ' L ' + x + ' ' + yv; }
      else { d += ' L ' + x + ' ' + yv; a += ' L ' + x + ' ' + yv; }
    }

    a += ' L ' + (padL + innerW) + ' ' + (padT + innerH) + ' Z';

    var area = svgEl('path');
    area.setAttribute('d', a);
    area.setAttribute('class', 'dash-area');
    svg.appendChild(area);

    var path = svgEl('path');
    path.setAttribute('d', d);
    path.setAttribute('class', 'dash-line');
    svg.appendChild(path);

    for (var k = 0; k < values.length; k++) {
      var cx = padL + (innerW * (values.length === 1 ? 0 : k / (values.length - 1)));
      var cy = padT + innerH - (innerH * ((values[k] || 0) / maxV));
      var dot = svgEl('circle');
      dot.setAttribute('cx', cx);
      dot.setAttribute('cy', cy);
      dot.setAttribute('r', 3.5);
      dot.setAttribute('class', 'dash-dotpt');
      svg.appendChild(dot);
    }

    var step = 3;
    if (labels.length <= 7) step = 1;
    if (labels.length > 14) step = 4;

    for (var t = 0; t < labels.length; t += step) {
      var tx = padL + (innerW * (labels.length === 1 ? 0 : t / (labels.length - 1)));
      var text = svgEl('text');
      text.setAttribute('x', tx);
      text.setAttribute('y', padT + innerH + 20);
      text.setAttribute('text-anchor', 'middle');
      text.setAttribute('class', 'dash-axis');
      text.textContent = labels[t] || '';
      svg.appendChild(text);
    }

    target.innerHTML = '';
    target.appendChild(svg);
  }

  function renderBars(target, labels, values, meta, legendTarget) {
    var w = Math.max(320, Math.floor(target.getBoundingClientRect().width || target.clientWidth || 640));
    var h = 220;

    var padL = 30, padR = 12, padT = 16, padB = 36;
    var innerW = Math.max(10, w - padL - padR);
    var innerH = Math.max(10, h - padT - padB);

    var svg = svgEl('svg');
    svg.setAttribute('viewBox', '0 0 ' + w + ' ' + h);
    svg.setAttribute('width', '100%');
    svg.setAttribute('height', h);

    for (var g = 0; g <= 4; g++) {
      var y = padT + (innerH * g / 4);
      var ln = svgEl('line');
      ln.setAttribute('x1', padL);
      ln.setAttribute('x2', padL + innerW);
      ln.setAttribute('y1', y);
      ln.setAttribute('y2', y);
      ln.setAttribute('class', 'dash-gridline');
      svg.appendChild(ln);
    }

    var n = values ? values.length : 0;
    var gap = 10;
    var barW = n > 0 ? Math.max(10, (innerW - gap * (n - 1)) / n) : innerW;

    for (var i = 0; i < n; i++) {
      var v = clamp(values[i] || 0, 0, 100);
      var x = padL + i * (barW + gap);
      var bh = innerH * (v / 100);
      var yb = padT + innerH - bh;

      var rect = svgEl('rect');
      rect.setAttribute('x', x);
      rect.setAttribute('y', yb);
      rect.setAttribute('width', barW);
      rect.setAttribute('height', bh);
      rect.setAttribute('rx', 10);
      rect.setAttribute('class', 'dash-bar');
      svg.appendChild(rect);

      var pct = svgEl('text');
      pct.setAttribute('x', x + barW / 2);
      pct.setAttribute('y', yb - 6);
      pct.setAttribute('text-anchor', 'middle');
      pct.setAttribute('class', 'dash-axis');
      pct.textContent = Math.round(v) + '%';
      svg.appendChild(pct);

      var lab = svgEl('text');
      lab.setAttribute('x', x + barW / 2);
      lab.setAttribute('y', padT + innerH + 24);
      lab.setAttribute('text-anchor', 'middle');
      lab.setAttribute('class', 'dash-axis');
      var short = labels[i] || '';
      if (short.length > 10) short = short.slice(0, 9) + '…';
      lab.textContent = short;
      svg.appendChild(lab);
    }

    target.innerHTML = '';
    target.appendChild(svg);

    if (legendTarget) {
      var html = '';
      for (var j = 0; j < (labels ? labels.length : 0); j++) {
        var m = (meta && meta[j]) ? meta[j] : {};
        var a = typeof m.avg_score === 'number' ? m.avg_score : 0;
        var tp = typeof m.total_points === 'number' ? m.total_points : 0;
        var s = typeof m.submissions === 'number' ? m.submissions : 0;

        html += '<div class="dash-legend__row"><div class="dash-legend__name">' +
          escapeHtml(labels[j] || '') +
          '</div><div class="dash-legend__meta">' +
          escapeHtml(String(a)) + ' / ' + escapeHtml(String(tp)) +
          ' <span class="dash-legend__dim">•</span> ' +
          escapeHtml(String(s)) + ' submissions</div></div>';
      }
      legendTarget.innerHTML = html;
    }
  }

  function setupCharts() {
    var trendEl = qs('#chartTrend');
    if (!trendEl) return;

    var raw = trendEl.getAttribute('data-payload');
    if (!raw) return;

    var data;
    try { data = JSON.parse(raw); } catch (e) { data = null; }
    if (!data) return;

    function draw() {
      var t = data.trend || { labels: [], values: [] };
      renderLine(trendEl, t.labels || [], t.values || []);

      var avgEl = qs('#chartAvg');
      var legendEl = qs('#avgLegend');
      var a = data.avg || { labels: [], values: [], meta: [] };
      if (avgEl) renderBars(avgEl, a.labels || [], a.values || [], a.meta || [], legendEl);
    }

    draw();
    window.addEventListener('resize', function () {
      window.requestAnimationFrame(function () { draw(); });
    }, { passive: true });
  }

  setupToasts();
  setupSearch();
  setupDrawer();
  setupCharts();
})();
</script>

</body>
</html>
