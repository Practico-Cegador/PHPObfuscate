<?php
/* 
    v.0223
    Copyright 2015 Ashley Byron Deans http://www.byrondeans.com
    
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Lesser General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Lesser General Public License for more details.

    You should have received a copy of the GNU Lesser General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

$dir = '.';
$files1 = scandir($dir);

$next_variable_name = 'a' . substr(md5(rand()), 0, 7);

//load the variables list file that will preload $variables_array, the array used to replace all the variables in the file with appropriate random values, so that a. it's possible to look up what the original variable name is later (for debugging) and b. the variable names, if desired, can be 1. set in advance by hand and 2. stay the same each time the script is run by using a file with all variables already in it.

if(in_array("variable_list_in.txt", $files1)) { //NOTE TO ANYONE RUNNING THIS SCRIPT: You can create your own variable_list_in.txt to load in your own choices OR copy variable_list_out.txt to get the exact same output every time

    $filecontents = fopen("variable_list_in.txt", "r");
    $variable_list_in_as_string = fread($filecontents, filesize("variable_list_in.txt"));

    //this regexp reads the original, human readable variable name in the file (the left one) and the replacement (the right one)
    $result = preg_match_all('/\$(.*) = \$(.*)/', $variable_list_in_as_string, $matches);

    //reading $matches[0] and then doing another preg_match on each element turned out to be the easiest way to get the pairs
    foreach($matches[0] as $value){

        preg_match('/\$(.*) = \$(.*)/', $value, $matches);
        $variables_array[$matches[1]] = $matches[2]; //the $variables_array is the array used to store the pairs of variables before switching human readable for obfuscated replacement

    }

}

foreach($files1 as $value){//go through all the files in the directory
    if(preg_match("/php$/", $value) || preg_match("/inc$/", $value)){//work on the .php and .inc files only
        
        
        if($value != 'compressor.php'){//don't compress the compressor itself
            $files2[] = $value;//this array, missing "compressor.php", will be used at the end of the file to erase the files from this loop
            
            //use the PHP -w feature to strip out whitespace and comments, saving result with a randomly chosen "-1" appended
            exec("php -w $value > $value-1");
            
            $filecontents = fopen("$value-1", "r");
            
            $file_as_string = fread($filecontents, filesize("$value-1"));
            
            //uses a regular expression from the PHP manual to find all variables
            $pattern = '/\${?([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/';
            preg_match_all($pattern, $file_as_string, $matches);
            
            //die(print_r($matches));
            
            foreach($matches[1] as $key => $value){//go through all the variables in the .php or .inc file we're on
                
                //does the variable have corresponding value in the $variables_array array?
                if(!isset($variables_array[$value]) && $value != '_POST' && $value != '_SESSION' && $value != '_GET'){
                    
                    //IF NOT
                    //give the variable's name a corresponding obscure/randomly generated name in the array, for later replacement
                    
                    $variables_array[$value] = $next_variable_name;
                    
                    //a new random name replacement for the next pass through the foreach
                    $next_variable_name = 'a' . substr(md5(rand()), 0, 7);
                     
                    //make sure the new random name doesn't already exist in the array of variable name replacements
                    $i = 0;
                     while($i == 0){
                        if(in_array($next_variable_name, $variables_array)){//although unlikely, the random variables might be identical: check to make sure  
                            $next_variable_name = 'a' . substr(md5(rand()), 0, 7);//if it does coincidentally have the same value as another randomly generated variable name, loop an unlimited # of times, trying different randomly generated names until it's unique
                        } else {
                            $i = 1;
                        }
                     }
                }
            }
            
            fclose($filecontents);
            

        }
        
    }
    
    
    
}

//this is the loop where the new variable names are inserted into each file
foreach($files2 as $value){

        $filecontents = fopen($value, "r");//first, open each file

        $file_as_string = fread($filecontents, filesize($value));

        foreach($variables_array as $key => $value2){//the loop that substitutes the new name for each occurrence of the old name in the file


            $file_as_string = str_replace('$' . $key, '$' . $value2, $file_as_string); 
            $file_as_string = str_replace('${' . $key . '}', '${' . $value2 . '}', $file_as_string);//this will catch variables like this: ${var}
        }

        
        if(!file_exists("processed")){//get ready to save all the files in the processed/ directory
            exec("mkdir processed");//if processed/ doesn't exist, create it
        }
            

        $filehandle = fopen('./processed/' . $value, "w");
        fwrite($filehandle, $file_as_string);
        fclose($filehandle);

}

//prepare the string that will be saved as variable_list_out.txt: a file with the matching original and replaced variable names that can be easily renamed to variable_list_in.txt, to tell the script what the replacement names should be
foreach($variables_array as $key => $value){

    $variable_list_out_as_string .= '$' . $key . ' = $' . $value . "\n";

}

$filehandle = fopen('variable_list_out.txt', "w");//write the original and new variable names according to the format $old = $new ea. pair on its own line

fwrite($filehandle, $variable_list_out_as_string);
fclose($filehandle);

foreach($files2 as $value){    
    exec("rm $value-1");//get rid of the files produced by php -w: copies are now in the processed/ directory, with variables switched
}

?>
