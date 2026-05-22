let $doc       := doc("C:/xampp/htdocs/6666/miniprojet/miniprojet_FIXED/club.xml")/club
let $categorie := "Intelligence Artificielle"
let $catId     := $doc/categories/categorie[@libelle = $categorie]/@id
return
<membres categorie="{$categorie}">{
  for $m in $doc/membres/membre[@categorieRef = $catId]
  order by $m/nom/text(), $m/prenom/text()
  return
    <membre id="{$m/@id}">
      <nom>{$m/nom/text()}</nom>
      <prenom>{$m/prenom/text()}</prenom>
      <email>{$m/email/text()}</email>
    </membre>
}</membres>
