let $doc := doc("C:/xampp/htdocs/6666/miniprojet/miniprojet_FIXED/club.xml")/club
return
<vainqueurs>{
  for $c in $doc/concours/concours
    let $coeff    := xs:decimal($c/@coefficient)
    let $maxScore := max(
      for $p in $c/participants/participant
      return ($p/complexite + $p/tempsExecution) * $coeff
    )
  return
    <concours titre="{$c/titre/text()}" scoreMax="{$maxScore}">{
      for $p in $c/participants/participant
        let $score := ($p/complexite + $p/tempsExecution) * $coeff
        let $m     := $doc/membres/membre[@id = $p/@membreRef]
      where $score = $maxScore
      return
        <vainqueur>
          <nom>{$m/nom/text()}</nom>
          <prenom>{$m/prenom/text()}</prenom>
          <score>{$score}</score>
        </vainqueur>
    }</concours>
}</vainqueurs>
