<?php

class SessionDetails
{
    public function getSession($dbo)
    {
        $rv=[];
        $c="select * from session_details";
        $s=$dbo->conn->prepare($c);
        try{
            $s->execute();
            $rv=$s->fetchAll(PDO::FETCH_ASSOC);//kuhaon ang result pero forma lang ug array

        }
        catch(Exception $e)
        {

        }
        return $rv;
    }
}
?> 
