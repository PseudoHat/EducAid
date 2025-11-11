<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
} header('Content-Type: application/json');
require_once __DIR__.'/../config/database.php';
require_once __DIR__ . '/../includes/CSRFProtection.php';
@include_once __DIR__.'/../includes/permissions.php';
function out($ok,$msg='',$extra=[]){echo json_encode(array_merge(['success'=>$ok,'message'=>$msg],$extra));exit;}
if($_SERVER['REQUEST_METHOD']!=='POST') out(false,'Invalid method');
// CSRF Protection
$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!CSRFProtection::validateToken('cms_content', $token, false)) out(false, 'Security validation failed. Please refresh the page.');
$is_super_admin=false; if(isset($_SESSION['admin_id']) && function_exists('getCurrentAdminRole')){ $role=@getCurrentAdminRole($connection); if($role==='super_admin') $is_super_admin=true; }
if(!$is_super_admin) out(false,'Unauthorized');
$raw=file_get_contents('php://input'); $data=json_decode($raw,true)?:[];
$block=trim($data['block']??''); $limit=(int)($data['limit']??50); if($limit<1)$limit=25; if($limit>200)$limit=200; $cursor=isset($data['cursor'])?(int)$data['cursor']:null; $actionType=trim($data['action_type']??'');
function s($h){ $h=preg_replace('#<script[^>]*>.*?</script>#is','',$h); $h=preg_replace('/on[a-zA-Z]+\s*=\s*"[^"]*"/i','',$h); $h=preg_replace("/on[a-zA-Z]+\s*=\s*'[^']*'/i",'', $h); $h=preg_replace('/javascript:/i','',$h); return $h; }
$clauses=['municipality_id=1']; $params=[]; if($block!==''){ $clauses[]='block_key=$'.(count($params)+1); $params[]=$block; } if($actionType!==''){ $clauses[]='action_type=$'.(count($params)+1); $params[]=$actionType; } if($cursor!==null && $cursor>0){ $clauses[]='audit_id < $'.(count($params)+1); $params[]=$cursor; }
function gen_sum($row){$a=$row['action_type'];$c=[];if($a==='update'){$ot=strip_tags($row['old_html']??'');$nt=strip_tags($row['new_html']??'');if($ot!==$nt){$ow=str_word_count($ot);$nw=str_word_count($nt);$d=$nw-$ow;if($d>0)$c[]="Added $d words";elseif($d<0)$c[]="Removed ".abs($d)." words";else $c[]="Modified text";}if(($row['old_text_color']??'')!==($row['new_text_color']??''))$c[]="Changed text color";if(($row['old_bg_color']??'')!==($row['new_bg_color']??''))$c[]="Changed background";return !empty($c)?implode(', ',$c):'Updated content';}if($a==='reset_all')return 'Reset all content to default';if($a==='rollback')return 'Rolled back to previous version';return 'Modified';}
$where=implode(' AND ',$clauses); $sql="SELECT audit_id, block_key, action_type, created_at, new_html, old_html, new_text_color, old_text_color, new_bg_color, old_bg_color, admin_username FROM about_content_audit WHERE $where ORDER BY audit_id DESC LIMIT ".($limit+1);
$res=$params?@pg_query_params($connection,$sql,$params):@pg_query($connection,$sql);
$records=[];$has_more=false; if($res){ while($row=pg_fetch_assoc($res)){ $content=$row['new_html']!==null && $row['new_html']!==''?$row['new_html']:($row['old_html']??''); $text_color=$row['new_text_color']??$row['old_text_color']??null; $bg_color=$row['new_bg_color']??$row['old_bg_color']??null; if(count($records)>=$limit){ $has_more=true; break; } $records[]=['audit_id'=>(int)$row['audit_id'],'block_key'=>$row['block_key'],'action_type'=>$row['action_type'],'created_at'=>$row['created_at'],'html'=>s($content),'text_color'=>$text_color,'bg_color'=>$bg_color,'admin_username'=>$row['admin_username']??'System','summary'=>gen_sum($row)]; }}
$next_cursor=null; if($has_more && $records){ $last=end($records); $next_cursor=$last['audit_id']; }
out(true,'OK',['records'=>$records,'count'=>count($records),'block'=>$block,'action_type'=>$actionType,'has_more'=>$has_more,'next_cursor'=>$next_cursor]);
