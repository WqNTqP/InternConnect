<?php

class SessionDetails
{
    public function getSession($dbo)
    {
        $rv=[];
        $c="select ID, YEAR from session_details ORDER BY YEAR DESC";
        $s=$dbo->conn->prepare($c);
        try{
            $s->execute();
            $data=$s->fetchAll(PDO::FETCH_ASSOC);
            
            // Format the output to display as "S.Y. YEAR-YEAR+1"
            foreach($data as $session) {
                $year = $session['YEAR'];
                $nextYear = $year + 1;
                $rv[] = [
                    'ID' => $session['ID'],
                    'YEAR' => $session['YEAR'],
                    'DISPLAY_NAME' => 'S.Y. ' . $year . '-' . $nextYear
                ];
            }

        }
        catch(Exception $e)
        {

        }
        return $rv;
    }
}
?> 
