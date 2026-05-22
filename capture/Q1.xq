let $doc := doc("C:/xampp/htdocs/6666/miniprojet/miniprojet_FIXED/club.xml")/club
return
<membres>{
  for $m in $doc/membres/membre
    let $cat := $doc/categories/categorie[@id = $m/@categorieRef]
  return
    <membre id="{$m/@id}">
      <nomComplet>{$m/prenom/text()} {$m/nom/text()}</nomComplet>
      <email>{$m/email/text()}</email>
      <categorie>{$cat/@libelle/string()}</categorie>
    </membre>
}</membres>
