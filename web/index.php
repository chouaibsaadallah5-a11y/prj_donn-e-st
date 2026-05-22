<?php
/**
 * Club Info_Tech — Application Web (VERSION CORRIGÉE)
 * ✅ Fix 1 : Inscription — suppression de la vérification catégorie
 * ✅ Fix 2 : Requêtes XQuery — exécution locale via PHP/SimpleXML (sans BaseX)
 */

// ── Chemins ──────────────────────────────────────────
define('XML_FILE', __DIR__ . '/../club.xml');

// ── Page active ───────────────────────────────────────
$page = $_GET['page'] ?? 'concours';
$allowed = ['concours','inscription','resultats','requetes'];
if (!in_array($page, $allowed)) $page = 'concours';

// ── Charger le XML ────────────────────────────────────
$xml = null;
$xmlError = null;
if (file_exists(XML_FILE)) {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_file(XML_FILE);
    if (!$xml) {
        $xmlError = implode("\n", array_map(fn($e) => $e->message, libxml_get_errors()));
    }
} else {
    $xmlError = "Fichier club.xml introuvable : " . XML_FILE;
}

// ── Statistiques ──────────────────────────────────────
$stats = ['categories' => 0, 'membres' => 0, 'concours' => 0, 'participants' => 0];
if ($xml) {
    $stats['categories']  = count($xml->categories->categorie);
    $stats['membres']     = count($xml->membres->membre);
    $stats['concours']    = count($xml->concours->concours);
    foreach ($xml->concours->concours as $c)
        $stats['participants'] += count($c->participants->participant);
}

// ── Fonctions utilitaires ─────────────────────────────
function getCatLibelle(SimpleXMLElement $xml, string $id): string {
    foreach ($xml->categories->categorie as $cat)
        if ((string)$cat['id'] === $id) return (string)$cat['libelle'];
    return $id;
}

function calcScore(SimpleXMLElement $p, float $coeff): float {
    return round(((float)$p->complexite + (float)$p->tempsExecution) * $coeff, 2);
}

function getMembre(SimpleXMLElement $xml, string $id): ?SimpleXMLElement {
    foreach ($xml->membres->membre as $m)
        if ((string)$m['id'] === $id) return $m;
    return null;
}

// ════════════════════════════════════════════════════════
// ✅ FIX 2 — Exécution XQuery locale via PHP/SimpleXML
//    Pas besoin de BaseX — tout tourne avec PHP
// ════════════════════════════════════════════════════════
function runQuery(string $queryKey, SimpleXMLElement $xml, string $q5cat = 'Intelligence Artificielle'): string {
    switch ($queryKey) {

        case 'Q1': // Liste des membres
            $out = "<membres>\n";
            foreach ($xml->membres->membre as $m) {
                $cat = getCatLibelle($xml, (string)$m['categorieRef']);
                $out .= "  <membre id=\"{$m['id']}\">\n";
                $out .= "    <nomComplet>{$m->prenom} {$m->nom}</nomComplet>\n";
                $out .= "    <email>{$m->email}</email>\n";
                $out .= "    <categorie>$cat</categorie>\n";
                $out .= "  </membre>\n";
            }
            return $out . "</membres>";

        case 'Q2': // Concours triés par date
            $list = [];
            foreach ($xml->concours->concours as $c) $list[] = $c;
            usort($list, fn($a,$b) => strcmp((string)$a['date'], (string)$b['date']));
            $out = "<concours>\n";
            foreach ($list as $c) {
                $cat = getCatLibelle($xml, (string)$c['categorieRef']);
                $out .= "  <concours id=\"{$c['id']}\">\n";
                $out .= "    <titre>{$c->titre}</titre>\n";
                $out .= "    <date>{$c['date']}</date>\n";
                $out .= "    <coefficient>{$c['coefficient']}</coefficient>\n";
                $out .= "    <categorie>$cat</categorie>\n";
                $out .= "  </concours>\n";
            }
            return $out . "</concours>";

        case 'Q3': // Calcul des scores
            $out = "<resultats>\n";
            foreach ($xml->concours->concours as $c) {
                $coeff = (float)$c['coefficient'];
                $out .= "  <concours titre=\"{$c->titre}\">\n";
                foreach ($c->participants->participant as $p) {
                    $m     = getMembre($xml, (string)$p['membreRef']);
                    $score = round(((float)$p->complexite + (float)$p->tempsExecution) * $coeff, 2);
                    $nom   = $m ? "{$m->prenom} {$m->nom}" : (string)$p['membreRef'];
                    $out .= "    <participant>\n";
                    $out .= "      <nom>$nom</nom>\n";
                    $out .= "      <complexite>{$p->complexite}</complexite>\n";
                    $out .= "      <tempsExecution>{$p->tempsExecution}</tempsExecution>\n";
                    $out .= "      <score>$score</score>\n";
                    $out .= "    </participant>\n";
                }
                $out .= "  </concours>\n";
            }
            return $out . "</resultats>";

        case 'Q4': // Vainqueurs
            $out = "<vainqueurs>\n";
            foreach ($xml->concours->concours as $c) {
                $coeff = (float)$c['coefficient'];
                $scores = [];
                foreach ($c->participants->participant as $p)
                    $scores[] = ((float)$p->complexite + (float)$p->tempsExecution) * $coeff;
                $max = max($scores);
                $out .= "  <concours titre=\"{$c->titre}\" scoreMax=\"$max\">\n";
                foreach ($c->participants->participant as $p) {
                    $score = ((float)$p->complexite + (float)$p->tempsExecution) * $coeff;
                    if ($score == $max) {
                        $m = getMembre($xml, (string)$p['membreRef']);
                        $nom = $m ? "{$m->prenom} {$m->nom}" : (string)$p['membreRef'];
                        $out .= "    <vainqueur>\n";
                        $out .= "      <nom>$nom</nom>\n";
                        $out .= "      <score>$score</score>\n";
                        $out .= "    </vainqueur>\n";
                    }
                }
                $out .= "  </concours>\n";
            }
            return $out . "</vainqueurs>";

        case 'Q5': // Membres par catégorie
            $catLib = $q5cat;
            $catId  = '';
            foreach ($xml->categories->categorie as $cat)
                if ((string)$cat['libelle'] === $catLib) { $catId = (string)$cat['id']; break; }
            $list = [];
            foreach ($xml->membres->membre as $m)
                if ((string)$m['categorieRef'] === $catId) $list[] = $m;
            usort($list, fn($a,$b) => strcmp((string)$a->nom.(string)$a->prenom, (string)$b->nom.(string)$b->prenom));
            $out = "<membres categorie=\"$catLib\">\n";
            foreach ($list as $m) {
                $out .= "  <membre id=\"{$m['id']}\">\n";
                $out .= "    <nom>{$m->nom}</nom>\n";
                $out .= "    <prenom>{$m->prenom}</prenom>\n";
                $out .= "    <email>{$m->email}</email>\n";
                $out .= "  </membre>\n";
            }
            return $out . "</membres>";

        default:
            return "Requête inconnue.";
    }
}

// ════════════════════════════════════════════════════════
// INSCRIPTION — ajout membre + participation concours
// ════════════════════════════════════════════════════════
$inscriptionMsg = null;
if ($page === 'inscription' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $action     = trim($_POST['action']     ?? 'inscrire');
    $concoursId = trim($_POST['concoursId'] ?? '');
    $complexite = (int)($_POST['complexite'] ?? 0);
    $temps      = (int)($_POST['tempsExecution'] ?? 0);

    $errors = [];

    // ── Générer le prochain ID membre automatiquement ──
    function nextMembreId(SimpleXMLElement $xml): string {
        $max = 0;
        foreach ($xml->membres->membre as $m) {
            $num = (int)substr((string)$m['id'], 1);
            if ($num > $max) $max = $num;
        }
        return 'M' . str_pad($max + 1, 3, '0', STR_PAD_LEFT);
    }

    if ($action === 'nouveau') {
        // ── Ajouter un nouveau membre ──
        $nom        = trim($_POST['nom']        ?? '');
        $prenom     = trim($_POST['prenom']     ?? '');
        $email      = trim($_POST['email']      ?? '');
        $categorieRef = trim($_POST['categorieRef'] ?? '');

        if (!$nom)          $errors[] = "Nom requis.";
        if (!$prenom)       $errors[] = "Prénom requis.";
        if (!$email)        $errors[] = "Email requis.";
        if (!$categorieRef) $errors[] = "Catégorie requise.";

        if (!$errors && $xml) {
            $newId = nextMembreId($xml);
            $newM  = $xml->membres->addChild('membre');
            $newM->addAttribute('id', $newId);
            $newM->addAttribute('categorieRef', $categorieRef);
            $newM->addChild('nom',    $nom);
            $newM->addChild('prenom', $prenom);
            $newM->addChild('email',  $email);

            $dom = new DOMDocument('1.0', 'UTF-8');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
            $dom->loadXML($xml->asXML());
            $dom->save(XML_FILE);
            $xml = simplexml_load_file(XML_FILE);
            $inscriptionMsg = ['type' => 'success', 'text' => "✅ Membre $newId ($prenom $nom) ajouté avec succès !"];
        } else if ($errors) {
            $inscriptionMsg = ['type' => 'error', 'text' => implode(' ', $errors)];
        }

    } else {
        // ── Inscrire un membre existant à un concours ──
        $membreRef = trim($_POST['membreRef'] ?? '');

        if (!$membreRef)                          $errors[] = "Membre requis.";
        if (!$concoursId)                         $errors[] = "Concours requis.";
        if ($complexite < 0 || $complexite > 100) $errors[] = "Complexité entre 0 et 100.";
        if ($temps <= 0)                          $errors[] = "Temps d'exécution doit être > 0.";

        if ($xml && !$errors) {
            $membre   = getMembre($xml, $membreRef);
            $concours = null;
            foreach ($xml->concours->concours as $c)
                if ((string)$c['id'] === $concoursId) { $concours = $c; break; }

            if (!$membre)   $errors[] = "Membre $membreRef introuvable.";
            if (!$concours) $errors[] = "Concours $concoursId introuvable.";

            if (!$errors && $concours) {
                foreach ($concours->participants->participant as $p)
                    if ((string)$p['membreRef'] === $membreRef) {
                        $errors[] = "Ce membre participe déjà à ce concours.";
                        break;
                    }
            }
        }

        if (!$errors && $xml) {
            foreach ($xml->concours->concours as $c) {
                if ((string)$c['id'] === $concoursId) {
                    $newP = $c->participants->addChild('participant');
                    $newP->addAttribute('membreRef', $membreRef);
                    $newP->addChild('complexite', (string)$complexite);
                    $newP->addChild('tempsExecution', (string)$temps);
                    break;
                }
            }
            $dom = new DOMDocument('1.0', 'UTF-8');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
            $dom->loadXML($xml->asXML());
            $dom->save(XML_FILE);
            $xml = simplexml_load_file(XML_FILE);
            $inscriptionMsg = ['type' => 'success', 'text' => "✅ Inscription enregistrée avec succès dans club.xml !"];
        } else if ($errors) {
            $inscriptionMsg = ['type' => 'error', 'text' => implode(' ', $errors)];
        }
    }
}

// ── نص Q5 ديناميكي حسب الكاتيغوري المختارة
$q5CatCurrent = $_POST['q5_categorie'] ?? 'Intelligence Artificielle';
$queryTexts['Q5'] = 'let $doc := doc("TP1/club.xml")/club
(: paramètre catégorie — modifiable :)
let $categorie := "' . $q5CatCurrent . '"
let $catId := $doc/categories/categorie[@libelle = $categorie]/@id
return
<membres categorie="{$categorie}">{
  for $m in $doc/membres/membre[@categorieRef = $catId]
  (: tri alphabétique par nom puis prénom :)
  order by $m/nom/text(), $m/prenom/text()
  return
    <membre id="{$m/@id}">
      <nom>{$m/nom/text()}</nom>
      <prenom>{$m/prenom/text()}</prenom>
      <email>{$m/email/text()}</email>
    </membre>
}</membres>';
$queryResult = null;
$selectedQ   = '';
if ($page === 'requetes' && $_SERVER['REQUEST_METHOD'] === 'POST' && $xml) {
    $selectedQ = $_POST['queryKey'] ?? '';
    if ($selectedQ) {
        $q5cat = $_POST['q5_categorie'] ?? 'Intelligence Artificielle';
        $queryResult = ['ok' => true, 'result' => runQuery($selectedQ, $xml, $q5cat)];
    }
}

// ── Textes XQuery affichés pour chaque requête ────────
$queryTexts = [
    'Q1' => 'let $doc := doc("TP1/club.xml")/club
return
<membres>{
  for $m in $doc/membres/membre
    (: jointure catégorie :)
    let $cat := $doc/categories/categorie[@id = $m/@categorieRef]
  return
    <membre id="{$m/@id}">
      <nomComplet>{$m/prenom/text()} {$m/nom/text()}</nomComplet>
      <email>{$m/email/text()}</email>
      <categorie>{$cat/@libelle/string()}</categorie>
    </membre>
}</membres>',

    'Q2' => 'let $doc := doc("TP1/club.xml")/club
return
<concours>{
  for $c in $doc/concours/concours
    let $cat := $doc/categories/categorie[@id = $c/@categorieRef]
  (: tri par date croissante :)
  order by xs:date($c/@date)
  return
    <concours id="{$c/@id}">
      <titre>{$c/titre/text()}</titre>
      <date>{$c/@date/string()}</date>
      <categorie>{$cat/@libelle/string()}</categorie>
    </concours>
}</concours>',

    'Q3' => 'let $doc := doc("TP1/club.xml")/club
return
<resultats>{
  for $c in $doc/concours/concours
    let $coeff := xs:decimal($c/@coefficient)
  return
    <concours titre="{$c/titre/text()}">{
      for $p in $c/participants/participant
        let $m := $doc/membres/membre[@id = $p/@membreRef]
        (: formule : score = (complexite + tempsExecution) × coefficient :)
        let $score := round(($p/complexite + $p/tempsExecution) * $coeff * 100) div 100
      return
        <participant>
          <nom>{$m/prenom/text()} {$m/nom/text()}</nom>
          <complexite>{$p/complexite/text()}</complexite>
          <tempsExecution>{$p/tempsExecution/text()}</tempsExecution>
          <score>{$score}</score>
        </participant>
    }</concours>
}</resultats>',

    'Q4' => 'let $doc := doc("TP1/club.xml")/club
return
<vainqueurs>{
  for $c in $doc/concours/concours
    let $coeff := xs:decimal($c/@coefficient)
    (: calcul du score max du concours :)
    let $maxScore := max(
      for $p in $c/participants/participant
      return ($p/complexite + $p/tempsExecution) * $coeff
    )
  return
    <concours titre="{$c/titre/text()}" scoreMax="{$maxScore}">{
      (: filtre les participants ex-aequo :)
      for $p in $c/participants/participant
        let $score := ($p/complexite + $p/tempsExecution) * $coeff
        let $m := $doc/membres/membre[@id = $p/@membreRef]
      where $score = $maxScore
      return
        <vainqueur>
          <nom>{$m/prenom/text()} {$m/nom/text()}</nom>
          <score>{$score}</score>
        </vainqueur>
    }</concours>
}</vainqueurs>',

    'Q5' => 'let $doc := doc("TP1/club.xml")/club
(: paramètre catégorie — modifiable :)
let $categorie := "Intelligence Artificielle"
let $catId := $doc/categories/categorie[@libelle = $categorie]/@id
return
<membres categorie="{$categorie}">{
  for $m in $doc/membres/membre[@categorieRef = $catId]
  (: tri alphabétique par nom puis prénom :)
  order by $m/nom/text(), $m/prenom/text()
  return
    <membre id="{$m/@id}">
      <nom>{$m/nom/text()}</nom>
      <prenom>{$m/prenom/text()}</prenom>
      <email>{$m/email/text()}</email>
    </membre>
}</membres>',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Club Info_Tech — Gestion XML</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-wrapper">

  <!-- ══ SIDEBAR ══ -->
  <aside class="sidebar">
    <div class="sidebar-logo">
      <div class="club-name">Info_Tech</div>
      <div class="club-sub">Club · XML Manager</div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-group-label">Navigation</div>
      <a href="?page=concours"   class="nav-link <?= $page==='concours'   ?'active':'' ?>">
        <span class="nav-icon">◈</span> Concours
        <?php if($stats['concours']>0): ?><span class="nav-badge"><?= $stats['concours'] ?></span><?php endif; ?>
      </a>
      <a href="?page=inscription" class="nav-link <?= $page==='inscription'?'active':'' ?>">
        <span class="nav-icon">✦</span> Inscription
      </a>
      <a href="?page=resultats"  class="nav-link <?= $page==='resultats'  ?'active':'' ?>">
        <span class="nav-icon">◎</span> Résultats
      </a>
      <div class="nav-group-label" style="margin-top:12px">Avancé</div>
      <a href="?page=requetes"   class="nav-link <?= $page==='requetes'   ?'active':'' ?>">
        <span class="nav-icon">⌥</span> Requêtes XQuery
      </a>
    </nav>
    <div class="sidebar-footer">
      Membres : <strong style="color:var(--teal)"><?= $stats['membres'] ?></strong><br>
      Participants : <strong style="color:var(--teal)"><?= $stats['participants'] ?></strong><br>
      <span style="color:var(--muted);font-size:10px">club.xml · PHP/SimpleXML</span>
    </div>
  </aside>

  <!-- ══ MAIN ══ -->
  <div class="main-content">
    <div class="topbar">
      <div>
        <div class="topbar-title">
          <?php $titles=['concours'=>'Concours','inscription'=>'Inscription','resultats'=>'Résultats','requetes'=>'Requêtes XQuery'];
          echo htmlspecialchars($titles[$page]); ?>
        </div>
        <div class="topbar-breadcrumb">Club Info_Tech / <span><?= htmlspecialchars($titles[$page]) ?></span></div>
      </div>
      <?php if($xmlError): ?>
      <div class="badge badge-red">⚠ XML non chargé</div>
      <?php else: ?>
      <div class="badge badge-green">✔ club.xml chargé</div>
      <?php endif; ?>
    </div>

    <div class="page">
      <?php if($xmlError): ?>
      <div class="alert alert-error"><?= nl2br(htmlspecialchars($xmlError)) ?></div>
      <?php endif; ?>

      <!-- ══ PAGE 1 — CONCOURS ══ -->
      <?php if($page === 'concours'): ?>
      <div class="stats-row">
        <div class="stat-card"><div class="stat-value"><?= $stats['categories'] ?></div><div class="stat-label">Catégories</div></div>
        <div class="stat-card"><div class="stat-value"><?= $stats['membres'] ?></div><div class="stat-label">Membres</div></div>
        <div class="stat-card"><div class="stat-value"><?= $stats['concours'] ?></div><div class="stat-label">Concours</div></div>
        <div class="stat-card"><div class="stat-value"><?= $stats['participants'] ?></div><div class="stat-label">Inscriptions</div></div>
      </div>

      <div class="section-header">
        <h2>Liste des concours</h2>
        <span class="tag">Triés par date</span>
      </div>

      <?php if($xml && $stats['concours'] > 0):
        $list = [];
        foreach ($xml->concours->concours as $c) $list[] = $c;
        usort($list, fn($a,$b) => strcmp((string)$a['date'], (string)$b['date']));
      ?>
      <div class="table-wrap">
        <div class="table-wrap-header">
          <h3>Concours disponibles</h3>
          <a href="?page=inscription" class="btn btn-outline" style="font-size:11px;padding:6px 14px;">+ Inscription</a>
        </div>
        <table>
          <thead><tr><th>ID</th><th>Titre</th><th>Date</th><th>Catégorie</th><th>Coefficient</th><th>Participants</th></tr></thead>
          <tbody>
          <?php foreach($list as $c): $catLib = getCatLibelle($xml,(string)$c['categorieRef']); ?>
            <tr>
              <td><span class="badge badge-teal"><?= htmlspecialchars((string)$c['id']) ?></span></td>
              <td style="color:var(--white);font-weight:600"><?= htmlspecialchars((string)$c->titre) ?></td>
              <td><?= htmlspecialchars((string)$c['date']) ?></td>
              <td><?= htmlspecialchars($catLib) ?></td>
              <td style="color:var(--amber);font-weight:700">× <?= htmlspecialchars((string)$c['coefficient']) ?></td>
              <td><span class="badge badge-green"><?= count($c->participants->participant) ?> inscrits</span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

      <!-- ══ PAGE 2 — INSCRIPTION ══ -->
      <?php elseif($page === 'inscription'): ?>
      <div class="section-header">
        <h2>Inscription à un concours</h2>
        <span class="tag">Persisté dans club.xml</span>
      </div>

      <?php if($inscriptionMsg): ?>
      <div class="alert alert-<?= $inscriptionMsg['type'] ?>"><?= htmlspecialchars($inscriptionMsg['text']) ?></div>
      <?php endif; ?>

      <?php if($xml): ?>

      <!-- ══ TABS ══ -->
      <div style="display:flex;gap:8px;margin-bottom:24px">
        <button onclick="showTab('inscrire')" id="tab-inscrire"
          class="btn btn-primary" style="font-size:12px;padding:8px 18px">
          ✦ Inscrire un membre
        </button>
        <button onclick="showTab('nouveau')" id="tab-nouveau"
          class="btn btn-outline" style="font-size:12px;padding:8px 18px">
          ＋ Nouveau membre
        </button>
      </div>

      <!-- ══ FORM 1 : Inscrire membre existant ══ -->
      <div id="form-inscrire">
      <div class="form-card">
        <form method="POST" action="?page=inscription">
          <input type="hidden" name="action" value="inscrire">
          <div class="form-group">
            <label class="form-label">Membre</label>
            <select name="membreRef" class="form-control" required>
              <option value="">— Choisir un membre —</option>
              <?php foreach($xml->membres->membre as $m):
                $sel = (isset($_POST['membreRef']) && $_POST['membreRef']==(string)$m['id']) ? 'selected':'';
                $nomAffiche = trim((string)$m->prenom.' '.(string)$m->nom);
                if ($nomAffiche === '') $nomAffiche = (string)$m['id'];
              ?>
              <option value="<?= htmlspecialchars((string)$m['id']) ?>" <?= $sel ?>>
                <?= htmlspecialchars($nomAffiche) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Concours</label>
            <select name="concoursId" class="form-control" required>
              <option value="">— Choisir un concours —</option>
              <?php foreach($xml->concours->concours as $c):
                $catLib = getCatLibelle($xml,(string)$c['categorieRef']);
                $sel = (isset($_POST['concoursId']) && $_POST['concoursId']==(string)$c['id']) ? 'selected':'';
              ?>
              <option value="<?= htmlspecialchars((string)$c['id']) ?>" <?= $sel ?>>
                <?= htmlspecialchars((string)$c->titre) ?> [<?= htmlspecialchars($catLib) ?>]
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div class="form-group">
              <label class="form-label">Complexité (0–100)</label>
              <input type="number" name="complexite" class="form-control" min="0" max="100" required
                     value="<?= isset($_POST['complexite']) ? (int)$_POST['complexite'] : '' ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Temps d'exécution (ms)</label>
              <input type="number" name="tempsExecution" class="form-control" min="1" required
                     value="<?= isset($_POST['tempsExecution']) ? (int)$_POST['tempsExecution'] : '' ?>">
            </div>
          </div>
          <button type="submit" class="btn btn-primary">✦ Enregistrer l'inscription</button>
        </form>
      </div>
      </div>

      <!-- ══ FORM 2 : Nouveau membre ══ -->
      <div id="form-nouveau" style="display:none">
      <div class="form-card">
        <form method="POST" action="?page=inscription">
          <input type="hidden" name="action" value="nouveau">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div class="form-group">
              <label class="form-label">Nom</label>
              <input type="text" name="nom" class="form-control" required
                     value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Prénom</label>
              <input type="text" name="prenom" class="form-control" required
                     value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Catégorie</label>
            <select name="categorieRef" class="form-control" required>
              <option value="">— Choisir une catégorie —</option>
              <?php foreach($xml->categories->categorie as $cat):
                $sel = (isset($_POST['categorieRef']) && $_POST['categorieRef']==(string)$cat['id']) ? 'selected':'';
              ?>
              <option value="<?= htmlspecialchars((string)$cat['id']) ?>" <?= $sel ?>>
                <?= htmlspecialchars((string)$cat['libelle']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div style="background:#0D1117;border:1px solid var(--border);border-radius:6px;padding:12px;margin-bottom:16px;font-size:12px;color:var(--muted)">
            🔖 ID généré automatiquement :
            <strong style="color:var(--teal)">
              <?php
                $ids = [];
                foreach($xml->membres->membre as $m) $ids[] = (int)substr((string)$m['id'],1);
                $nextId = 'M' . str_pad((max($ids)+1), 3, '0', STR_PAD_LEFT);
                echo $nextId;
              ?>
            </strong>
          </div>
          <button type="submit" class="btn btn-primary">＋ Ajouter le membre</button>
        </form>
      </div>
      </div>

      <script>
      function showTab(tab) {
        document.getElementById('form-inscrire').style.display = tab==='inscrire' ? 'block' : 'none';
        document.getElementById('form-nouveau').style.display  = tab==='nouveau'  ? 'block' : 'none';
        document.getElementById('tab-inscrire').className = 'btn ' + (tab==='inscrire' ? 'btn-primary' : 'btn-outline');
        document.getElementById('tab-nouveau').className  = 'btn ' + (tab==='nouveau'  ? 'btn-primary' : 'btn-outline');
      }
      <?php if(($_POST['action']??'')==='nouveau'): ?>
      showTab('nouveau');
      <?php endif; ?>
      </script>

      <?php endif; ?>

      <!-- ══ PAGE 3 — RÉSULTATS ══ -->
      <?php elseif($page === 'resultats'): ?>
      <div class="section-header">
        <h2>Résultats par concours</h2>
        <span class="tag">Score = (complexité + temps) × coeff</span>
      </div>

      <div class="form-card" style="max-width:400px;padding:20px;margin-bottom:28px">
        <form method="GET">
          <input type="hidden" name="page" value="resultats">
          <div class="form-group" style="margin-bottom:12px">
            <label class="form-label">Sélectionner un concours</label>
            <select name="concoursId" class="form-control" onchange="this.form.submit()">
              <option value="">— Tous les concours —</option>
              <?php if($xml): foreach($xml->concours->concours as $c): ?>
              <option value="<?= htmlspecialchars((string)$c['id']) ?>"
                <?= (($_GET['concoursId']??'')===(string)$c['id'])?'selected':'' ?>>
                <?= htmlspecialchars((string)$c->titre) ?>
              </option>
              <?php endforeach; endif; ?>
            </select>
          </div>
        </form>
      </div>

      <?php if($xml):
        $filterCo = $_GET['concoursId'] ?? '';
        $shown = 0;
        foreach($xml->concours->concours as $c):
          if($filterCo && (string)$c['id'] !== $filterCo) continue;
          $shown++;
          $coeff = (float)$c['coefficient'];
          $catLib = getCatLibelle($xml,(string)$c['categorieRef']);
          $rows = [];
          foreach($c->participants->participant as $p) {
              $m = getMembre($xml,(string)$p['membreRef']);
              $rows[] = [
                  'nom'   => $m ? (string)$m->prenom.' '.(string)$m->nom : (string)$p['membreRef'],
                  'compl' => (int)$p->complexite,
                  'temps' => (int)$p->tempsExecution,
                  'score' => round(((int)$p->complexite+(int)$p->tempsExecution)*$coeff,2),
              ];
          }
          usort($rows, fn($a,$b) => $b['score'] <=> $a['score']);
          $maxScore = $rows ? $rows[0]['score'] : 0;
      ?>
      <div class="table-wrap" style="margin-bottom:28px">
        <div class="table-wrap-header">
          <h3>
            <?= htmlspecialchars((string)$c->titre) ?>
            <span class="badge badge-teal" style="margin-left:10px;font-size:9px"><?= htmlspecialchars($catLib) ?></span>
            <span style="color:var(--muted);font-size:11px;margin-left:10px">
              <?= htmlspecialchars((string)$c['date']) ?> · coeff × <?= htmlspecialchars((string)$c['coefficient']) ?>
            </span>
          </h3>
        </div>
        <table>
          <thead><tr><th>#</th><th>Participant</th><th>Complexité</th><th>Temps (ms)</th><th>Score</th><th>Rang</th></tr></thead>
          <tbody>
          <?php foreach($rows as $rank => $r):
            $isWin = ($r['score'] === $maxScore);
            $pct   = $maxScore>0 ? min(100,round($r['score']/$maxScore*100)) : 0;
          ?>
            <tr>
              <td style="color:var(--muted)"><?= $rank+1 ?></td>
              <td style="color:var(--white);font-weight:600">
                <?php if($isWin): ?><span class="winner-icon">★</span> <?php endif; ?>
                <?= htmlspecialchars($r['nom']) ?>
              </td>
              <td><?= $r['compl'] ?></td>
              <td><?= $r['temps'] ?></td>
              <td>
                <div class="score-bar-wrap">
                  <div class="score-bar"><div class="score-bar-fill" style="width:<?= $pct ?>%"></div></div>
                  <span class="score-num"><?= number_format($r['score'],2) ?></span>
                </div>
              </td>
              <td>
                <?php if($isWin): ?><span class="badge badge-amber">🏆 Vainqueur</span>
                <?php elseif($rank===1): ?><span class="badge badge-teal">2e</span>
                <?php elseif($rank===2): ?><span class="badge badge-green">3e</span>
                <?php else: ?><span style="color:var(--muted)"><?= $rank+1 ?>e</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endforeach;
        if($shown===0): ?>
      <div class="empty-state"><div class="empty-icon">◌</div><p>Aucun résultat.</p></div>
      <?php endif; endif; ?>

      <!-- ══ PAGE 4 — REQUÊTES XQUERY ══ -->
      <?php elseif($page === 'requetes'): ?>
      <div class="section-header">
        <h2>Requêtes XQuery</h2>
      </div>

      <?php
      $queries = [
          'Q1' => 'Q1 — Liste de tous les membres',
          'Q2' => 'Q2 — Concours triés par date',
          'Q3' => 'Q3 — Scores de tous les participants',
          'Q4' => 'Q4 — Vainqueur de chaque concours',
          'Q5' => 'Q5 — Membres par catégorie (triés)',
      ];
      ?>

      <!-- boutons Q1→Q5 -->
      <form method="POST" action="?page=requetes" id="formRequetes">
        <input type="hidden" name="queryKey" id="selectedQKey" value="<?= htmlspecialchars($selectedQ) ?>">
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px">
          <?php foreach($queries as $key => $label): ?>
          <button type="submit" name="queryKey" value="<?= $key ?>"
                  class="btn <?= $selectedQ===$key?'btn-primary':'btn-outline' ?>"
                  style="font-size:12px;padding:8px 16px"
                  onclick="document.getElementById('selectedQKey').value='<?= $key ?>'">
            <?= htmlspecialchars($label) ?>
          </button>
          <?php endforeach; ?>
        </div>

        <!-- Sélecteur catégorie visible uniquement pour Q5 -->
        <div id="q5-options" style="<?= $selectedQ==='Q5'?'':'display:none;' ?>margin-bottom:20px">
          <div class="form-card" style="padding:16px;max-width:400px">
            <label class="form-label">Catégorie (Q5)</label>
            <select class="form-control" name="q5_categorie" onchange="this.form.submit()">
              <?php if($xml): foreach($xml->categories->categorie as $cat):
                $lib = (string)$cat['libelle'];
                $cur = $_POST['q5_categorie'] ?? 'Intelligence Artificielle';
                $sel = $lib === $cur ? 'selected' : '';
              ?>
              <option value="<?= htmlspecialchars($lib) ?>" <?= $sel ?>>
                <?= htmlspecialchars($lib) ?>
              </option>
              <?php endforeach; endif; ?>
            </select>
          </div>
        </div>
      </form>

      <script>
        // Afficher/masquer le sélecteur Q5
        document.querySelectorAll('button[name="queryKey"]').forEach(btn => {
          btn.addEventListener('click', () => {
            document.getElementById('q5-options').style.display =
              btn.value === 'Q5' ? 'block' : 'none';
          });
        });
      </script>

      <?php if($selectedQ && isset($queryTexts[$selectedQ])): ?>
      <!-- Layout 2 colonnes : requête | résultat -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:8px">

        <!-- Colonne gauche : texte XQuery -->
        <div>
          <div class="table-wrap">
            <div class="table-wrap-header">
              <h3>Requête XQuery — <?= htmlspecialchars($queries[$selectedQ]) ?></h3>
            </div>
            <div style="padding:16px">
              <pre style="background:#0D1117;border:1px solid var(--border);border-radius:6px;
                          padding:14px;font-family:'JetBrains Mono',monospace;font-size:11.5px;
                          color:#e2e8f0;line-height:1.7;overflow-x:auto;white-space:pre-wrap;
                          margin:0"><?= htmlspecialchars($queryTexts[$selectedQ]) ?></pre>
            </div>
          </div>
        </div>

        <!-- Colonne droite : résultat -->
        <div>
          <div class="table-wrap">
            <div class="table-wrap-header" style="justify-content:space-between">
              <h3>Résultat XML</h3>
              <?php if($queryResult && $queryResult['ok']): ?>
              <span class="badge badge-green">✔ OK</span>
              <?php endif; ?>
            </div>
            <div style="padding:16px">
              <?php if($queryResult): ?>
              <div class="result-box" style="max-height:400px"><?= htmlspecialchars($queryResult['result']) ?></div>
              <?php else: ?>
              <div class="empty-state"><div class="empty-icon">◌</div><p>Cliquez sur Exécuter.</p></div>
              <?php endif; ?>
            </div>
          </div>
        </div>

      </div>
      <?php elseif(!$selectedQ): ?>
      <div class="empty-state" style="margin-top:20px">
        <div class="empty-icon">⌥</div>
        <p>Cliquez sur une requête pour l'exécuter.</p>
      </div>
      <?php endif; ?>

      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
