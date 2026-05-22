let $doc := doc("C:/xampp/htdocs/6666/miniprojet/miniprojet_FIXED/club.xml")/club
return
<concours>{
  for $c in $doc/concours/concours
    let $cat := $doc/categories/categorie[@id = $c/@categorieRef]
  order by xs:date($c/@date)
  return
    <concours id="{$c/@id}">
      <titre>{$c/titre/text()}</titre>
      <date>{$c/@date/string()}</date>
      <coefficient>{$c/@coefficient/string()}</coefficient>
      <categorie>{$cat/@libelle/string()}</categorie>
    </concours>
}</concours>
