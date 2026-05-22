let $doc := doc("C:/xampp/htdocs/6666/miniprojet/miniprojet_FIXED/club.xml")/club
return
<resultats>{
  for $c in $doc/concours/concours
    let $coeff := xs:decimal($c/@coefficient)
  return
    <concours titre="{$c/titre/text()}">{
      for $p in $c/participants/participant
        let $m     := $doc/membres/membre[@id = $p/@membreRef]
        let $compl := xs:integer($p/complexite)
        let $temps := xs:integer($p/tempsExecution)
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
