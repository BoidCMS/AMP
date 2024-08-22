<?php defined( 'App' ) or die( 'BoidCMS' );
global $App;
$ampify = ampify( $App->page( 'content' ) );
?>
<!DOCTYPE html>
<html ⚡ lang="<?= $App->get( 'lang' ) ?>">
  <head>
    <meta charset="utf-8">
    <meta name="generator" content="BoidCMS">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= $App->page( 'descr' ) ?>">
    <meta property="og:type" content="article">
    <meta property="og:locale" content="<?= $App->get( 'lang' ) ?>">
    <meta property="og:title" content="<?= $App->page( 'title' ) ?>">
    <meta property="og:description" content="<?= $App->page( 'descr' ) ?>">
    <?php if ( $App->page( 'thumb' ) ): ?>
    <meta property="og:image" content="<?= $App->url( 'media/' . $App->page( 'thumb' ) ) ?>">
    <?php endif ?>
    <meta property="og:url" content="<?= $App->url( $App->page ) ?>">
    <meta property="og:site_name" content="<?= $App->get( 'title' ) ?>">
    <link rel="preload" as="script" href="https://cdn.ampproject.org/v0.js">
    <script async src="https://cdn.ampproject.org/v0.js"></script>
    <?= $ampify[1] ?>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "BlogPosting",
      "mainEntityOfPage": {
        "@type": "WebPage",
        "@id": "<?= $App->url( $App->page ) ?>"
      },
      "headline": "<?= $App->page( 'title' ) ?>",
      "description": "<?= $App->page( 'descr' ) ?>",
      <?php if ( $App->page( 'thumb' ) ): ?>
      "image": "<?= $App->url( 'media/' . $App->page( 'thumb' ) ) ?>",
      <?php endif ?>
      "author": {
        "@type": "Organization",
        "name": "<?= $App->get( 'title' ) ?>",
        "url": "<?= $App->url() ?>"
      },
      "publisher": {
        "@type": "Organization",
        "name": "<?= $App->get( 'title' ) ?>",
        "logo": {
          "@type": "ImageObject",
          "url": "<?= $App->url( 'media/logo.png' ) ?>"
        }
      },
      "datePublished": "<?= $App->page( 'date' ) ?>",
      "dateModified": "<?= $App->page( 'date' ) ?>"
    }
    </script>
    <style amp-custom>html{line-height:1.15;-webkit-text-size-adjust:100%}body{margin:0}details,fieldset label,fieldset legend,figure img,main{display:block}h1{font-size:2em;margin:.67em 0}hr{box-sizing:content-box;height:0;border-bottom:1px solid #595959}code,kbd,pre,samp{font-family:monospace,monospace;font-size:1em}a{background-color:transparent;color:#275a90;text-decoration:none}abbr[title]{border-bottom:none;text-decoration:underline dotted}small{font-size:80%}img{border-style:none;height:auto;margin:0 auto}button,input,optgroup,select,textarea{font-family:inherit;font-size:100%;line-height:1.15;margin:0}button,hr,input{overflow:visible}button,select{text-transform:none}[type=button],[type=reset],[type=submit],button{-webkit-appearance:button}[type=button]::-moz-focus-inner,[type=reset]::-moz-focus-inner,[type=submit]::-moz-focus-inner,button::-moz-focus-inner{border-style:none;padding:0}[type=button]:-moz-focusring,[type=reset]:-moz-focusring,[type=submit]:-moz-focusring,button:-moz-focusring{outline:1px dotted ButtonText}fieldset{padding:.35em .75em .625em}legend{color:inherit;display:table;max-width:100%;white-space:normal}*,progress,sub,sup{vertical-align:baseline}textarea{overflow:auto}[type=checkbox],[type=radio],legend{box-sizing:border-box;padding:0}[type=number]::-webkit-inner-spin-button,[type=number]::-webkit-outer-spin-button{height:auto}[type=search]{-webkit-appearance:textfield;outline-offset:-2px}[type=search]::-webkit-search-decoration{-webkit-appearance:none}::-webkit-file-upload-button{-webkit-appearance:button;font:inherit}summary{display:list-item}[hidden],template{display:none}body,button,input,option,select,textarea{font:400 1.125rem/1.85625rem system-ui,"Helvetica Neue",Helvetica,Arial,sans-serif}b,dt,strong,th{font-weight:600}td,th{border-bottom:0 solid #595959;padding:.928125rem 1.125rem;text-align:left;vertical-align:top}thead th{padding-bottom:.39375rem}table{display:table;width:100%}@media all and (max-width:1024px){table,table thead{display:none}}table tr,table tr th,thead th{border-bottom-width:.135rem}table tr td,table tr th{overflow:hidden;padding:.3375rem .225rem}@media all and (max-width:1024px){table tr td,table tr th{border:0;display:inline-block}table tr{margin:.675rem 0}table,table tr{display:inline-block}}fieldset legend{margin:1.125rem 0}input{padding:.61875rem}input,select,textarea{display:inline-block}button,input,select,textarea{border-radius:3.6px}button+input[type=checkbox],button+input[type=radio],button+label,input+input[type=checkbox],input+input[type=radio],input+label,label+*,select+input[type=checkbox],select+input[type=radio],select+label,textarea+input[type=checkbox],textarea+input[type=radio],textarea+label{page-break-before:always}input,label,select{margin-right:.225rem}textarea{min-height:5.625rem;min-width:22.5rem}label{display:inline-block;margin-bottom:.7875rem}label>input{margin-bottom:0}button,input[type=reset],input[type=submit]{background:#f2f2f2;cursor:pointer;display:inline;margin-bottom:1.125rem;margin-right:.45rem;padding:.4078125rem 1.4625rem;text-align:center}button,input[type=reset]{color:#191919}input[type=submit]:hover{background:#d9d9d9}button:hover,input[type=reset]:hover{background:#d9d9d9;color:#000}button[disabled],input[type=reset][disabled],input[type=submit][disabled]{background:#e6e5e5;color:#403f3f;cursor:not-allowed}button[type=submit],input[type=submit]{background:#275a90;color:#fff}button[type=submit]:hover,input[type=submit]:hover{background:#173454;color:#bfbfbf}h1,h2,h3,h4,h5,input,select,textarea{margin-bottom:1.125rem}input[type=color],input[type=date],input[type=datetime-local],input[type=datetime],input[type=email],input[type=file],input[type=month],input[type=number],input[type=password],input[type=phone],input[type=range],input[type=search],input[type=tel],input[type=text],input[type=time],input[type=url],input[type=week],select,textarea{border:1px solid #595959;padding:.3375rem .39375rem}input[type=checkbox],input[type=radio]{flex-grow:0;height:1.85625rem;margin-left:0;margin-right:9px;vertical-align:middle}input[type=checkbox]+label,input[type=radio]+label{page-break-before:avoid}select[multiple]{min-width:270px}code,kbd,output,pre,samp,var{font:.9rem Menlo,Monaco,Consolas,"Courier New",monospace}pre{border-left:0 solid #59c072;line-height:1.575rem;overflow:auto;padding-left:18px}*,pre code{border:0;padding:0}pre code{background:0 0;line-height:1.85625rem}code{color:#2a6f3b}code,kbd,nav ul li{display:inline-block}code,kbd{background:#daf1e0;border-radius:3.6px;line-height:1.125rem;padding:.225rem .39375rem .16875rem}kbd{background:#2a6f3b;color:#fff}mark{background:#ffc;padding:0 .225rem}h1,h2,h3,h4,h5{color:#000}h5,h6{font-size:.9rem;font-weight:700;text-transform:uppercase}h6{margin-bottom:1.125rem;line-height:1.125rem}h1{font-size:2.25rem;font-weight:500;line-height:2.7rem;margin-top:4.5rem}h2{font-size:1.575rem;font-weight:400;line-height:2.1375rem;margin-top:3.375rem}h3{font-size:1.35rem;line-height:1.6875rem;margin-top:2.25rem}h4{font-size:1.125rem;line-height:1.4625rem;margin-top:1.125rem}h5{line-height:1.35rem}a:hover,ins,u{text-decoration:underline}figcaption,small{font-size:.95625rem}figcaption,h6{color:#595959}em,i,var{font-style:italic}del,s{text-decoration:line-through}sub,sup{font-size:75%;line-height:0;position:relative}sup{top:-.5em}sub{bottom:-.25em}*{border-collapse:separate;border-spacing:0;box-sizing:border-box;margin:0;max-width:100%}body,html{width:100%}html{height:100%}body{color:#1a1919;padding:36px}address,blockquote,dl,fieldset,figure,form,hr,ol,p,pre,table,ul{margin-bottom:1.85625rem}section{margin-left:auto;margin-right:auto;width:900px}aside{float:right;width:285px}article,footer,header{padding:43.2px}footer,header,nav,nav ul{text-align:center}article,body{background:#fff}article{border:1px solid #d9d9d9;width:70%;margin:auto}nav ul{list-style:none;margin-left:0}nav ul li{margin-left:9px;margin-right:9px;vertical-align:middle}nav ul li:first-child{margin-left:0}nav ul li:last-child{margin-right:0}ol,ul{margin-left:31.5px}blockquote p,li dl,li ol,li ul{margin-bottom:0}dl{display:inline-block}dt{padding:0 1.125rem}dd{padding:0 1.125rem .28125rem}dd:last-of-type{border-bottom:0 solid #595959}dd+dt{border-top:0 solid #595959;padding-top:.5625rem}blockquote{border-left:0 solid #595959;padding:.28125rem 1.125rem .28125rem .99rem}blockquote footer{color:#595959;font-size:.84375rem;margin:0}@media (max-width:767px){body{padding:18px 0}article{border:0;width:90%;margin:auto}article,footer,header{padding:18px}fieldset,input,select,textarea{min-width:0}fieldset *{flex-grow:1;page-break-before:auto}section{width:auto}x:-moz-any-link{display:table-cell}}</style>
    <style amp-boilerplate>body{-webkit-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-moz-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-ms-animation:-amp-start 8s steps(1,end) 0s 1 normal both;animation:-amp-start 8s steps(1,end) 0s 1 normal both}@-webkit-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-moz-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-ms-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-o-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}</style><noscript><style amp-boilerplate>body{-webkit-animation:none;-moz-animation:none;-ms-animation:none;animation:none}</style></noscript>
    <link rel="canonical" href="<?= $App->url( $App->page ) ?>">
    <title><?= $App->page( 'title' ) ?></title>
    <?= $App->get_action( 'amp_head' ) ?>
  </head>
  <body>
    <?= $App->get_action( 'amp_top' ) ?>
    <header>
      <h1><?= $App->page( 'title' ) ?></h1>
      <p><small><time datetime="<?= $App->page( 'date' ) ?>"><?= date( 'F j, Y', strtotime( $App->page( 'date' ) ) ) ?></time></small></p>
      <?php if ( $App->page( 'thumb' ) ): ?>
        <?= ampify( sprintf( '<img src="%s" alt="Hero" data-hero>', $App->url( 'media/' . $App->page( 'thumb' ) ) ) ) ?>
      <?php endif ?>
    </header>
    <article>
      <?= $App->get_action( 'amp_content_top' ) ?>
      <?= $ampify[0] ?>
      <?= $App->get_action( 'amp_content_end' ) ?>
    </article>
    <footer>
      <p><?= $App->page( 'footer' ) ?></p>
      <p><small><a href="<?= $App->url( $App->page ) ?>" rel="canonical">View Full Version</a></small></p>
    </footer>
    <?= $App->get_action( 'amp_end' ) ?>
  </body>
</html>
