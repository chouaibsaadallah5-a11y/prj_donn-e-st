(: ================================================================
   FICHIER : requetes.xq
   Projet  : Gestion du Club Info_Tech
   Base    : BaseX — club.xml
   ================================================================ :)

(: ────────────────────────────────────────────────────────────────
   Q1 — Liste complète des membres  (1 pt)
   Affiche id, nom complet, email et libellé de catégorie de chaque
   membre (jointure avec <categories> via categorieRef).
   ──────────────────────────────────────────────────────────────── :)
(: Q1 :)
let $doc := doc("club.xml")/club          (: racine du document :)
return
<membres>{
  for $m in $doc/membres/membre           (: itère chaque membre :)
    (: jointure : récupère la catégorie dont l'id = categorieRef du membre :)
    let $cat := $doc/categories/categorie[@id = $m/@categorieRef]
  return
    <membre id="{$m/@id}">
      <nomComplet>{$m/prenom/text()} {$m/nom/text()}</nomComplet>
      <email>{$m/email/text()}</email>
      <categorie>{$cat/@libelle/string()}</categorie>
    </membre>
}</membres>

(: ────────────────────────────────────────────────────────────────
   Q2 — Liste des concours triés par date croissante  (1 pt)
   Affiche titre, date, coefficient et libellé de catégorie de
   chaque concours, triés par date (order by xs:date).
   ──────────────────────────────────────────────────────────────── :)
(: Q2 :)
let $doc := doc("club.xml")/club
return
<concours>{
  for $c in $doc/concours/concours        (: itère chaque concours :)
    (: jointure : récupère la catégorie du concours :)
    let $cat := $doc/categories/categorie[@id = $c/@categorieRef]
  order by xs:date($c/@date)              (: tri par date ISO croissante :)
  return
    <concours id="{$c/@id}">
      <titre>{$c/titre/text()}</titre>
      <date>{$c/@date/string()}</date>
      <coefficient>{$c/@coefficient/string()}</coefficient>
      <categorie>{$cat/@libelle/string()}</categorie>
    </concours>
}</concours>

(: ────────────────────────────────────────────────────────────────
   Q3 — Calcul des scores de tous les participants  (2 pts)
   Formule : score = (complexite + tempsExecution) × coefficient
   Affiche titre du concours, nom du participant, complexité,
   temps d'exécution et score arrondi à 2 décimales.
   ──────────────────────────────────────────────────────────────── :)
(: Q3 :)
let $doc := doc("club.xml")/club
return
<resultats>{
  for $c in $doc/concours/concours        (: itère les concours :)
    let $coeff := xs:decimal($c/@coefficient)
  return
    <concours titre="{$c/titre/text()}">{
      for $p in $c/participants/participant  (: itère les participants du concours :)
        (: récupère le membre correspondant via membreRef :)
        let $m     := $doc/membres/membre[@id = $p/@membreRef]
        let $compl := xs:integer($p/complexite)
        let $temps := xs:integer($p/tempsExecution)
        (: calcul du score et arrondi à 2 décimales :)
        let $score := round(($compl + $temps) * $coeff * 100) div 100
      return
        <participant>
          <nom>{$m/prenom/text()} {$m/nom/text()}</nom>
          <complexite>{$compl}</complexite>
          <tempsExecution>{$temps}</tempsExecution>
          <score>{$score}</score>
        </participant>
    }</concours>
}</resultats>

(: ────────────────────────────────────────────────────────────────
   Q4 — Vainqueur de chaque concours  (2 pts)
   Utilise max() pour trouver le score maximum, puis filtre les
   participants dont le score = max (gère les ex-aequo).
   ──────────────────────────────────────────────────────────────── :)
(: Q4 :)
let $doc := doc("club.xml")/club
return
<vainqueurs>{
  for $c in $doc/concours/concours
    let $coeff := xs:decimal($c/@coefficient)
    (: calcul du score pour chaque participant du concours :)
    let $scores :=
      for $p in $c/participants/participant
      return ($p/complexite + $p/tempsExecution) * $coeff
    (: score maximum du concours :)
    let $maxScore := max($scores)
  return
    <concours titre="{$c/titre/text()}" scoreMax="{$maxScore}">{
      (: filtre les participants ayant le score maximum (ex-aequo inclus) :)
      for $p in $c/participants/participant
        let $score := ($p/complexite + $p/tempsExecution) * $coeff
        let $m     := $doc/membres/membre[@id = $p/@membreRef]
      where $score = $maxScore              (: condition ex-aequo :)
      return
        <vainqueur>
          <nom>{$m/nom/text()}</nom>
          <prenom>{$m/prenom/text()}</prenom>
          <score>{$score}</score>
        </vainqueur>
    }</concours>
}</vainqueurs>

(: ────────────────────────────────────────────────────────────────
   Q5 — Membres d'une catégorie, triés alphabétiquement  (2 pts)
   Paramètre $categorie : libellé de la catégorie recherchée.
   Résultats triés d'abord par nom, ensuite par prénom.
   ──────────────────────────────────────────────────────────────── :)
(: Q5 :)
let $doc      := doc("club.xml")/club
(: ── PARAMÈTRE : modifier cette valeur pour changer de catégorie ── :)
let $categorie := "Intelligence Artificielle"
(: résoudre l'id de la catégorie à partir de son libellé :)
let $catId    := $doc/categories/categorie[@libelle = $categorie]/@id
return
<membres categorie="{$categorie}">{
  for $m in $doc/membres/membre[@categorieRef = $catId]  (: filtre par catégorie :)
  order by $m/nom/text(), $m/prenom/text()               (: tri alpha nom puis prénom :)
  return
    <membre id="{$m/@id}">
      <nom>{$m/nom/text()}</nom>
      <prenom>{$m/prenom/text()}</prenom>
      <email>{$m/email/text()}</email>
    </membre>
}</membres>
