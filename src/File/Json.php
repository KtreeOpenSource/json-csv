<?php

namespace OzdemirBurak\JsonCsv\File;

use OzdemirBurak\JsonCsv\AbstractFile;

class Json extends AbstractFile
{
    /**
     * @var array
     */
    protected $conversion = ['extension' => 'csv', 'type' => 'text/csv', 'delimiter' => ',', 'enclosure' => '"', 'escape' => '\\'];

    /**
     * @return string
     */
    public function convert() : string
    {

        $jsonData = $this->buidJson($this->data);

        return $this->toCsvString($jsonData);
    }

    public function buidJson($data)
    {
        $dataArray = json_decode($data, true);
        $result = $subResult = $commonResult = [];
        $pattern = '';
        $count = 0;
		//echo '<pre>';print_r($dataArray);echo '</pre>';
        foreach ($dataArray as $dataArrayKey => $dataArrayValue) {
			$count = 0;
            if (is_array($dataArrayValue)) {
                $pattern = $dataArrayKey.'_';
                $result = $this->buildFormat($dataArrayValue, $pattern, $subResult, $commonResult, $count, $result);
                $subResult = $result;
                 //echo '<pre>';print_r($subResult);die;

            } else {
                $commonResult[$dataArrayKey] = $dataArrayValue;
                $subResult = $commonResult;
            }
        }
		//echo 'result<pre>';print_r($result);echo '</pre>';exit;
        $resultArray = $this->formatArray($result);
        $res = $this->formatArray($resultArray, true);
        return $res;
    }
    
    public function buildFormat($data, $pattern, $subResult, $commonResult, $count, $result) {
		$commonResult2 = [];
		foreach ($data as $valueKey => $value) {
			if (is_array($value)) {
                $pattern = (is_numeric($valueKey)) ? $pattern : $pattern.'_'.$valueKey;

                if (is_numeric($valueKey)) {
					$response = $this->format($value, $pattern, $result, $commonResult, $subResult, $count);
					$result = $response['result'];
					$count = $response['count'];
                      
                } else {
                      foreach ($value as $key2 => $value2) {
                         $pattern = (is_numeric($key2)) ? $pattern : $pattern.'_'.$key2;

                         if (is_numeric($key2)) {
                           $result[$count] = $this->buildSubArray($subResult, $key2, $commonResult, $value2, $pattern);
                         } else {
							 $result[$count][$pattern] = $value2;
						 }
                      }
                      $count++;
                }
            } else {
               $commonResult2[$pattern.'_'.$valueKey] = $value;
               $subResult = array_merge($subResult, $commonResult2);
			}
        }
        
        return $result;
	}
	
	public function format($data, $pattern, $result, $commonResult, $subResult, $count) {
		$invoiceSubResult = [];
		$invoiceResult = [];
		$invoiceCommonResult = [];
		$invoicePattern = '';
		
		foreach ($data as $key => $value) {
            if (is_array($value)) {
				$invoicePattern = (is_numeric($key)) ? $invoicePattern : $invoicePattern.'_'.$key;
				foreach ($value as $key2 => $value2) {
					$invoicePattern = (is_numeric($key2)) ? $invoicePattern : $invoicePattern.'_'.$key2;
					$invoiceResult[$count] = $this->buildSubArray($invoiceSubResult, $key2, $invoiceCommonResult, $value2, $invoicePattern);
					$count++;
				}
				$invoiceSubResult = $invoiceResult;
			} else {
				$invoiceCommonResult[$key] = $value;
				$invoiceSubResult = $invoiceCommonResult;
			}
			
        }
        

        foreach ($invoiceResult as $invoiceResultKey => $invoiceResultValue) {
			$result[$invoiceResultKey] = $this->buildSubArray($subResult, $invoiceResultKey, $commonResult, $invoiceResultValue, $pattern);
		}
		
        return ['result' => $result, 'count' => $count];                  
	}
 
    public function buildSubArray($subResult, $valueKey, $commonResult, $value, $pattern)
    {

        if ($this->isMultidimentional($subResult)) {
            if (isset($subResult[$valueKey])) {
                $subResultArray = $subResult[$valueKey];
            } else {
                $subArray = end($subResult);
                if (is_array($subArray)) {
                    foreach ($subArray as $subResultKey => $subResultValue) {
                        if (isset ($commonResult[$subResultKey])) {
                            $subResultArray[$subResultKey] = $commonResult[$subResultKey];
                        } else {
                            $subResultArray[$subResultKey] = '';
                        }

                    }
                } else {
                    $subResultArray = $subArray;
                }
            }
        } else {
            $subResultArray = $subResult;
        }

        return $this->buildJsonArrayData($value, $pattern, $subResultArray);
    }
    
    public function buildJsonArrayData($data, $pattern = '', $result)
    {
		if (is_array($data)) {
			foreach ($data as $key => $value) {
				if(is_array($value)) {
				  $pattern = (is_numeric($key)) ? $pattern : $pattern.'_'.$key;
				  $result = array_merge($result, $this->buildJsonArrayData($value, $pattern, $result));
				} else {
					$result[$pattern."_".$key] = $value;
				}
			}
		}
        return $result;
    }

    public function formatArray($result, $replace = false)
    {

        foreach($result as $key => $value) {
            $index[$key] = sizeof($value);
        }
        $maxs = array_keys($index, max($index));

        $maxs = end($maxs);

        foreach ($result as $key => $value) {
          $array2 = isset($result[$maxs]) ? $result[$maxs] : [];
          if ($array2) {
            if ($replace === false) {
              foreach ($array2 as $array2Key => $array2Value) {
                  if (!isset($result[$key][$array2Key])) {
                      $result[$key][$array2Key] = '';
                  }
              }
            } else {
				$myarr = array_flip(array_keys($array2));
				$myarr = array_map(create_function('$n', 'return null;'), $myarr);

                $result[$key] = array_replace($myarr, $result[$key]);
            }

          }

        }
        return $result;
    }

    public function isMultidimentional($array) {
        foreach ($array as $value) {
            if (is_array($value)) {
              return true;
            }
        }
        return false;
    }

    

    /**
     * @param array $data
     *
     * @return string
     */
    protected function toCsvString(array $data) : string
    {
        $f = fopen('php://temp', 'w');
        fputcsv($f, array_keys(current($data)));
        foreach ($data as $row) {
            fputcsv($f, $row);
        }
        rewind($f);
        $csv = stream_get_contents($f);
        fclose($f);
        return ! \is_bool($csv) ? $csv : '';
    }

    /**
     * @param array  $array
     * @param string $prefix
     * @param array  $result
     *
     * @return array
     */
    protected function flatten(array $array = [], $prefix = '', array $result = []) : array
    {
        foreach ($array as $key => $value) {
            if (\is_array($value)) {
                $result = array_merge($result, $this->flatten($value, $prefix . $key . '_'));
            } else {
                $result[$prefix . $key] = $value;
            }
        }
        return $result;
    }
}
