(: ================================================================
   FICHIER : updates.xq
   Projet  : Gestion du Club Info_Tech
   Base    : BaseX — XQuery Update Facility
   Usage   : Exécuter dans BaseX GUI ou : basex -u updates.xq
   ================================================================ :)

(: ────────────────────────────────────────────────────────────────
   OPÉRATION 1 — INSERTION d'un nouveau membre  (1,5 pt)
   Ajoute le membre M013 (Zerrouk Lyna, catégorie C2 : Dév. Web)
   dans la liste <membres> du document.
   L'id M013 respecte le format Mxxx et est unique dans le document.
   ──────────────────────────────────────────────────────────────── :)

(: État AVANT : M013 n'existe pas dans <membres>
   Vérification : count(doc("club.xml")//membre[@id="M013"]) = 0     :)

insert node
  <membre id="M013" categorieRef="C2">
    <nom>Zerrouk</nom>
    <prenom>Lyna</prenom>
    <email>l.zerrouk@club.dz</email>
  </membre>
into doc("club.xml")//membres,           (: destination : nœud <membres> :)

(: ────────────────────────────────────────────────────────────────
   OPÉRATION 2 — MODIFICATION du coefficient du concours CO2  (1,5 pt)
   Change le coefficient de CO2 de 1.2 à 2.0.
   Utilise replace value of node pour modifier uniquement la valeur
   de l'attribut, sans toucher au reste de l'élément.
   ──────────────────────────────────────────────────────────────── :)

(: État AVANT : doc("club.xml")//concours[@id="CO2"]/@coefficient = "1.2"
   État APRÈS  : doc("club.xml")//concours[@id="CO2"]/@coefficient = "2.0"   :)

replace value of node
  doc("club.xml")//concours[@id="CO2"]/@coefficient   (: cible : attribut :)
with "2.0",                                           (: nouvelle valeur  :)

(: ────────────────────────────────────────────────────────────────
   OPÉRATION 3 — SUPPRESSION d'un participant  (1 pt)
   Retire le participant M003 du concours CO1.
   Le concours CO1 subsiste avec ses autres participants (M001, M002).
   ──────────────────────────────────────────────────────────────── :)

(: État AVANT : CO1 contient participants M001, M002, M003
   État APRÈS  : CO1 contient uniquement M001 et M002                         :)

delete node
  doc("club.xml")//concours[@id="CO1"]            (: localise le concours CO1 :)
    //participant[@membreRef="M003"]               (: cible le participant M003 :)
