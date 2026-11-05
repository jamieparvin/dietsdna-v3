@php
    $seoTitle = 'DietsDNA | Personalised Diet Plans & Nutrition Guidance';
    $seoDescription = 'Personalised meal plans shaped by your goals, nutrition profile and food preferences, with macro targets, saved meals, shopping lists and weight tracking.';
    $seoKeywords = 'DietsDNA, personalised meal plans, macro targets, food preferences, shopping lists, weight tracking';
    $seoUrl = url('/');

    $foodGroups = [
        ['name' => 'Vegetables', 'icon' => '🥦', 'foods' => ['Broccoli', 'Carrots', 'Courgette', 'Peppers', 'Spinach', 'Tomatoes']],
        ['name' => 'Protein', 'icon' => '🥚', 'foods' => ['Chicken', 'Eggs', 'Lentils', 'Salmon', 'Tofu', 'Turkey']],
        ['name' => 'Carbohydrates', 'icon' => '🌾', 'foods' => ['Brown rice', 'Oats', 'Potatoes', 'Quinoa', 'Sweet potato', 'Wholemeal pasta']],
        ['name' => 'Fruit & extras', 'icon' => '🥑', 'foods' => ['Apples', 'Avocado', 'Bananas', 'Blueberries', 'Olive oil', 'Pumpkin seeds']],
    ];

    $faqs = [
        ['question' => 'Do I need a personal trainer to use DietsDNA?', 'answer' => 'No. You can create your profile, choose food preferences and organise meals yourself. DietsDNA provides nutrition planning tools; it does not replace individual medical advice or care from a qualified professional.'],
        ['question' => 'Does DietsDNA use a DNA test?', 'answer' => 'No DNA test is needed. The plans described here use your nutrition profile, goals and food preferences.'],
        ['question' => 'What is DietsDNA?', 'answer' => 'DietsDNA helps you organise your nutrition with personal calorie and macro targets, food preferences, meal ideas, saved meals, weekly planning, shopping lists and weight tracking.'],
        ['question' => 'What information will my plan use?', 'answer' => 'Your nutrition profile includes details such as your age, height, weight, activity level and goals. Your food preferences help shape the meals suggested to you.'],
        ['question' => 'Can I choose the foods in my plan?', 'answer' => 'Yes. Set your food preferences, explore meal ideas and save meals you would like to use in your weekly plan.'],
        ['question' => 'Can I keep my favourite meals?', 'answer' => 'Yes. Save meals to return to them and use them when organising your meal plan.'],
        ['question' => 'Does DietsDNA include a shopping list?', 'answer' => 'Yes. Your shopping list brings together ingredients for your planned meals, with items you can check off as you shop.'],
        ['question' => 'Can I track my weight?', 'answer' => 'Yes. Record your weight entries and review your progress over time. You can update your profile and goals as your needs change.'],
        ['question' => 'Are the previews on this page my actual plan?', 'answer' => 'No. The planner and food examples on this page are illustrative. Your own plan and saved preferences are available inside your account.'],
    ];
    $startUrl = auth()->check() ? route('meal-plan.index') : (Route::has('register') ? route('register') : route('login'));
    $startLabel = auth()->check() ? 'Find my meal plans' : (Route::has('register') ? 'Create your account' : 'Log in');
@endphp
@include('partials.header')

<style>
/* All homepage styles are scoped to avoid changing the shared header or footer. */
.nc-home{--nc-green:#153f32;--nc-green-2:#286449;--nc-lime:#dbeeb9;--nc-cream:#faf8f2;--nc-ink:#23382e;--nc-muted:#6b786f;--nc-line:#e2e7dc;--nc-peach:#edbfa3;font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:var(--nc-ink);background:var(--nc-cream);font-size:16px;line-height:1.65;overflow:hidden;text-align:left}
.nc-home *{box-sizing:border-box}.nc-home h1,.nc-home h2,.nc-home h3,.nc-home p{margin:0}.nc-home button,.nc-home input{font:inherit}.nc-home a{text-decoration:none}.nc-home button{cursor:pointer}.nc-home img{max-width:100%;display:block}.nc-home :focus-visible{outline:3px solid #d9864c;outline-offset:5px}.nc-home .nc-wrap{width:min(1220px,calc(100% - 64px));margin:0 auto}.nc-home .nc-section{padding:92px 0;scroll-margin-top:110px}
.nc-home .nc-eyebrow{display:flex;align-items:center;gap:9px;color:var(--nc-green-2);font-size:11px;font-weight:750;letter-spacing:2px;text-transform:uppercase;margin-bottom:22px}.nc-home .nc-dot{width:7px;height:7px;background:currentColor;border-radius:50%;flex:none}.nc-home .nc-display{font-family:Georgia,"Times New Roman",serif;font-weight:400;letter-spacing:-2.5px;line-height:1.08}.nc-home h2.nc-display{font-size:clamp(35px,4.3vw,53px)}.nc-home .nc-copy{color:var(--nc-muted);font-size:17px;max-width:530px}.nc-home .nc-btn{display:inline-flex;align-items:center;justify-content:center;gap:18px;min-height:52px;border-radius:7px;padding:13px 23px;font-weight:650;font-size:14px;border:1px solid transparent;transition:background .2s,transform .2s}.nc-home .nc-btn:hover{transform:translateY(-2px)}.nc-home .nc-btn-primary{background:var(--nc-green);color:#fff}.nc-home .nc-btn-primary:hover{background:var(--nc-green-2);color:#fff}.nc-home .nc-btn-outline{color:var(--nc-green);border-color:#ced8c7;background:transparent}.nc-home .nc-btn-outline:hover{background:#eef2e8;color:var(--nc-green)}.nc-home .nc-arrow{font-size:19px;font-weight:400}.nc-home .nc-actions{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
/* Hero */
.nc-home .nc-hero{padding:76px 0 64px;position:relative}.nc-home .nc-hero-grid{display:grid;grid-template-columns:1fr 1fr;gap:66px;align-items:center}.nc-home .nc-hero h1{font-size:clamp(48px,5.4vw,72px);margin-bottom:26px}.nc-home .nc-hero h1 em{color:var(--nc-green-2);font-weight:400}.nc-home .nc-hero .nc-copy{margin-bottom:31px}.nc-home .nc-hero-note{display:flex;gap:19px;flex-wrap:wrap;font-size:12px;color:var(--nc-muted);margin-top:24px}.nc-home .nc-check{display:inline-grid;place-items:center;width:17px;height:17px;border-radius:50%;background:#e4ecd9;color:var(--nc-green);font-size:11px;margin-right:6px}
.nc-home .nc-hero-visual{position:relative;padding:27px 20px 33px;min-width:0}.nc-home .nc-hero-visual:before{content:"";position:absolute;inset:0 -14px 0 8px;background:#e9eedf;border-radius:47% 47% 22% 35%;transform:rotate(-5deg)}.nc-home .nc-planner{position:relative;background:#fff;border:1px solid #e6e9df;border-radius:17px;box-shadow:0 18px 65px #1b432814;overflow:hidden;transform:rotate(1.4deg)}.nc-home .nc-planner-top{padding:19px 22px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #eef1e8}.nc-home .nc-mini-brand{font-size:15px;font-weight:750;letter-spacing:-.5px;color:var(--nc-green)}.nc-home .nc-mini-brand i{font-style:normal;display:inline-grid;place-items:center;background:var(--nc-green);color:#dceebb;width:24px;height:24px;border-radius:7px;margin-right:7px;font-size:15px}.nc-home .nc-mini-pill{font-size:10px;border:1px solid var(--nc-line);border-radius:20px;padding:3px 9px;color:var(--nc-muted)}.nc-home .nc-planner-body{padding:22px}.nc-home .nc-planner-title{display:flex;justify-content:space-between;align-items:start;margin-bottom:18px}.nc-home .nc-planner-title h3{font-size:20px;font-weight:650;letter-spacing:-.5px}.nc-home .nc-small{font-size:11px;color:var(--nc-muted)}.nc-home .nc-days{display:grid;grid-template-columns:repeat(7,1fr);gap:5px;margin-bottom:19px}.nc-home .nc-day{text-align:center;padding:8px 3px;font-size:10px;background:#f7f8f3;border-radius:7px;color:var(--nc-muted)}.nc-home .nc-day b{display:block;font-size:14px;font-weight:650;color:var(--nc-ink)}.nc-home .nc-day.is-active{background:var(--nc-green);color:#c0d4b9}.nc-home .nc-day.is-active b{color:#fff}.nc-home .nc-meal-photo{height:175px;position:relative;border-radius:11px;background:#d8e2cc;overflow:hidden}.nc-home .nc-meal-photo img{width:100%;height:100%;object-fit:cover;object-position:50% 48%}.nc-home .nc-photo-label{position:absolute;bottom:11px;left:12px;background:#fffef5ed;border-radius:5px;padding:4px 9px;font-size:10px;font-weight:650}.nc-home .nc-featured-meal{padding:14px 0 13px}.nc-home .nc-featured-meal h4{margin:0;font-size:16px;font-weight:650;color:var(--nc-ink)}.nc-home .nc-meal-meta{display:flex;gap:11px;margin-top:5px;font-size:11px;color:var(--nc-muted)}.nc-home .nc-mini-meals{display:grid;grid-template-columns:1fr 1fr;gap:9px}.nc-home .nc-mini-meal{background:#f7f8f2;border-radius:9px;padding:11px;display:flex;gap:8px;align-items:center}.nc-home .nc-mini-meal .nc-food-icon{font-size:23px}.nc-home .nc-mini-meal strong{font-size:11px;display:block;line-height:1.35}.nc-home .nc-mini-meal small{font-size:9px;color:var(--nc-muted)}
.nc-home .nc-floating{position:absolute;z-index:2;right:-18px;top:72px;background:#fff;border:1px solid #e4e9db;border-radius:11px;padding:13px 18px;box-shadow:0 10px 25px #24463112;transform:rotate(-4deg);display:flex;align-items:center;gap:11px}.nc-home .nc-floating-icon{width:35px;height:35px;border-radius:50%;display:grid;place-items:center;background:#eef3e4;font-size:20px}.nc-home .nc-floating strong{display:block;font-size:12px}.nc-home .nc-floating small{display:block;font-size:10px;color:var(--nc-muted)}.nc-home .nc-example-caption{position:relative;margin-top:13px;text-align:center;font-size:10px;color:var(--nc-muted)}
/* Benefits ribbon */
.nc-home .nc-ribbon{border-top:1px solid var(--nc-line);border-bottom:1px solid var(--nc-line);background:#f2f4e9}.nc-home .nc-ribbon-inner{display:grid;grid-template-columns:repeat(4,1fr);padding:22px 0;gap:20px}.nc-home .nc-ribbon-item{display:flex;align-items:center;gap:12px;font-size:12px;font-weight:650}.nc-home .nc-ribbon-number{font-family:Georgia,serif;color:var(--nc-green-2);font-size:23px;font-style:italic}
/* Intro */
.nc-home .nc-section-heading{display:flex;justify-content:space-between;align-items:end;gap:45px;margin-bottom:36px}.nc-home .nc-section-heading .nc-copy{max-width:370px;font-size:15px}.nc-home .nc-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:21px}.nc-home .nc-feature{border:1px solid var(--nc-line);border-radius:12px;padding:29px;background:#fffefb}.nc-home .nc-feature-icon{width:43px;height:43px;background:#eef2e5;color:var(--nc-green);border-radius:11px;display:grid;place-items:center;margin-bottom:24px}.nc-home .nc-feature-icon svg{width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}.nc-home .nc-feature h3{font-size:19px;letter-spacing:-.4px;margin-bottom:12px;font-weight:650}.nc-home .nc-feature p{font-size:14px;color:var(--nc-muted)}.nc-home .nc-feature-tag{display:inline-flex;font-size:10px;margin-top:21px;padding:4px 9px;border-radius:4px;color:var(--nc-green-2);background:#eff3e6}
/* Interactive food preference preview */
.nc-home .nc-food-section{background:#eef1e5}.nc-home .nc-food-grid{display:grid;grid-template-columns:.85fr 1.15fr;gap:82px;align-items:center}.nc-home .nc-food-grid h2{margin-bottom:24px}.nc-home .nc-food-grid .nc-copy{font-size:16px;margin-bottom:25px}.nc-home .nc-food-notes{display:grid;gap:13px;font-size:13px}.nc-home .nc-food-panel{background:#fff;border:1px solid #e1e6d6;border-radius:15px;padding:27px;box-shadow:0 12px 40px #304b2410}.nc-home .nc-food-panel-title{display:flex;justify-content:space-between;align-items:center;margin-bottom:19px}.nc-home .nc-food-panel-title strong{font-size:16px;letter-spacing:-.3px}.nc-home .nc-selected-count{font-size:10px;color:var(--nc-green-2);background:#edf3e3;border-radius:20px;padding:5px 9px}.nc-home .nc-food-group+.nc-food-group{margin-top:18px}.nc-home .nc-food-label{font-size:10px;letter-spacing:1.1px;text-transform:uppercase;font-weight:700;color:var(--nc-muted);margin-bottom:8px}.nc-home .nc-food-chips{display:flex;gap:7px;flex-wrap:wrap}.nc-home .nc-chip{font-size:11px;line-height:1.4;border:1px solid #e1e6db;border-radius:6px;background:#fff;color:#657266;padding:7px 10px;display:flex;align-items:center;gap:7px;transition:background .15s,border-color .15s}.nc-home .nc-chip:before{content:"+";font-size:13px;line-height:1}.nc-home .nc-chip[aria-pressed=true]{background:#edf3e4;border-color:#b8cda2;color:var(--nc-green)}.nc-home .nc-chip[aria-pressed=true]:before{content:"✓";font-size:11px}.nc-home .nc-demo-note{font-size:10px;color:var(--nc-muted);padding-top:15px;margin-top:17px;border-top:1px solid #edf0e8}
/* How it works */
.nc-home .nc-how{text-align:center}.nc-home .nc-how .nc-eyebrow{justify-content:center}.nc-home .nc-how h2{max-width:600px;margin:auto}.nc-home .nc-steps{display:grid;grid-template-columns:repeat(3,1fr);gap:58px;margin-top:48px;text-align:left}.nc-home .nc-step-number{display:flex;align-items:center;gap:17px;color:var(--nc-green-2);font-family:Georgia,serif;font-size:35px;margin-bottom:21px}.nc-home .nc-step-number:after{content:"";height:1px;background:#dce2d1;flex:1}.nc-home .nc-step h3{font-size:18px;margin-bottom:11px;font-weight:650}.nc-home .nc-step p{font-size:14px;color:var(--nc-muted)}
/* Recipe preview */
.nc-home .nc-meals-section{padding-top:0}.nc-home .nc-recipe-grid{display:grid;grid-template-columns:1.15fr .85fr;border:1px solid var(--nc-line);border-radius:16px;overflow:hidden;background:#fff}.nc-home .nc-recipe-image{min-height:385px;position:relative;background:#d9e6cb}.nc-home .nc-recipe-image img{width:100%;height:100%;position:absolute;object-fit:cover}.nc-home .nc-recipe-sticker{position:absolute;top:24px;left:24px;background:#fffef4e8;padding:7px 13px;border-radius:20px;font-size:11px;color:var(--nc-green)}.nc-home .nc-recipe-text{padding:49px;display:flex;flex-direction:column;justify-content:center}.nc-home .nc-recipe-text h2{font-size:39px;margin-bottom:19px}.nc-home .nc-recipe-text p{font-size:15px;color:var(--nc-muted);margin-bottom:24px}.nc-home .nc-recipe-tags{display:flex;gap:7px;flex-wrap:wrap}.nc-home .nc-recipe-tags span{font-size:10px;border:1px solid var(--nc-line);padding:6px 9px;border-radius:5px}.nc-home .nc-recipe-example{font-size:10px;color:var(--nc-muted);margin-top:24px}
/* Goals + progress */
.nc-home .nc-progress-section{background:var(--nc-green);color:#fff}.nc-home .nc-progress-grid{display:grid;grid-template-columns:1fr 1fr;align-items:center;gap:85px}.nc-home .nc-progress-section .nc-eyebrow{color:var(--nc-lime)}.nc-home .nc-progress-section h2{margin-bottom:24px}.nc-home .nc-progress-section .nc-copy{color:#c5d3c6;font-size:16px}.nc-home .nc-goal-list{display:flex;gap:8px;flex-wrap:wrap;margin-top:25px}.nc-home .nc-goal-list span{font-size:11px;border:1px solid #496656;padding:7px 11px;border-radius:20px;color:#dce7d9}.nc-home .nc-journal{background:#fafbf5;border-radius:14px;padding:29px;color:var(--nc-ink);border:1px solid #647e5f}.nc-home .nc-journal-head{display:flex;justify-content:space-between;gap:15px;margin-bottom:23px}.nc-home .nc-journal-head strong{font-size:18px;letter-spacing:-.4px}.nc-home .nc-journal-subtitle{font-size:11px;color:var(--nc-muted)}.nc-home .nc-journal-row{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:14px 0;border-top:1px solid #e1e8d7}.nc-home .nc-journal-row strong{font-size:12px;font-weight:600}.nc-home .nc-journal-row small{font-size:10px;color:var(--nc-muted);display:block}.nc-home .nc-journal-tick{width:27px;height:27px;border-radius:50%;background:#e6efd9;color:var(--nc-green-2);display:grid;place-items:center;font-size:13px}.nc-home .nc-journal-message{font-family:Georgia,serif;font-size:21px;line-height:1.35;border-radius:9px;background:#edf2e4;padding:18px;margin-top:16px}.nc-home .nc-journal-example{color:var(--nc-muted);font-size:10px;margin-top:15px}
/* FAQs and final call to action */
.nc-home .nc-faq-grid{display:grid;grid-template-columns:.75fr 1.25fr;gap:90px}.nc-home .nc-faq-grid h2{margin-bottom:20px}.nc-home .nc-faq-intro{font-size:15px;color:var(--nc-muted)}.nc-home .nc-faq{border-bottom:1px solid var(--nc-line)}.nc-home .nc-faq:first-child{border-top:1px solid var(--nc-line)}.nc-home .nc-faq summary{display:flex;justify-content:space-between;gap:20px;align-items:center;list-style:none;cursor:pointer;padding:20px 0;font-size:15px;font-weight:600}.nc-home .nc-faq summary::-webkit-details-marker{display:none}.nc-home .nc-faq summary:after{content:"+";font-weight:400;font-size:22px;color:var(--nc-green-2);flex:none}.nc-home .nc-faq[open] summary:after{content:"−"}.nc-home .nc-faq p{font-size:14px;line-height:1.75;color:var(--nc-muted);padding:0 30px 23px 0}.nc-home .nc-cta-section{padding:0 0 85px}.nc-home .nc-cta{background:#e4edce;border-radius:15px;padding:54px;display:flex;justify-content:space-between;align-items:center;gap:38px}.nc-home .nc-cta h2{font-size:42px;max-width:590px}.nc-home .nc-cta p{font-size:14px;margin-top:15px;color:#607058}.nc-home .nc-cta .nc-btn{flex:none}.nc-home .nc-launch-note{font-size:11px;color:var(--nc-muted);margin-top:20px;text-align:center}
@media(min-width:1450px){.nc-home .nc-wrap{width:min(1320px,calc(100% - 96px))}.nc-home .nc-hero-grid{gap:110px}}
@media(max-width:1100px){.nc-home .nc-hero-grid{gap:35px}.nc-home .nc-floating{right:-5px}.nc-home .nc-food-grid,.nc-home .nc-progress-grid,.nc-home .nc-faq-grid{gap:45px}.nc-home .nc-recipe-text{padding:35px}.nc-home .nc-steps{gap:32px}.nc-home .nc-ribbon-item{font-size:11px}}
@media(max-width:850px){.nc-home .nc-wrap{width:calc(100% - 40px)}.nc-home .nc-section{padding:65px 0}.nc-home .nc-hero{padding:45px 0}.nc-home .nc-hero-grid{grid-template-columns:1fr;gap:24px}.nc-home .nc-hero h1{max-width:600px}.nc-home .nc-hero-visual{max-width:520px;width:100%;margin:0 auto}.nc-home .nc-section-heading{display:block}.nc-home .nc-section-heading .nc-copy{margin-top:20px;max-width:600px}.nc-home .nc-food-grid,.nc-home .nc-progress-grid,.nc-home .nc-faq-grid{grid-template-columns:1fr;gap:34px}.nc-home .nc-cards{gap:13px}.nc-home .nc-feature{padding:22px}.nc-home .nc-feature h3{font-size:17px}.nc-home .nc-ribbon-inner{grid-template-columns:1fr 1fr}.nc-home .nc-ribbon-item{font-size:12px}.nc-home .nc-recipe-grid{grid-template-columns:1fr}.nc-home .nc-recipe-image{min-height:310px}.nc-home .nc-meals-section{padding-top:0}.nc-home .nc-cta{padding:35px;flex-direction:column;align-items:start}.nc-home .nc-cta-section{padding:0 0 55px}.nc-home .nc-cta h2{font-size:35px}}
@media(max-width:580px){.nc-home .nc-wrap{width:calc(100% - 32px)}.nc-home .nc-hero h1{font-size:49px;letter-spacing:-2px}.nc-home .nc-copy{font-size:16px}.nc-home .nc-display{letter-spacing:-1.5px}.nc-home .nc-cards,.nc-home .nc-steps{grid-template-columns:1fr;gap:16px}.nc-home .nc-feature{padding:25px}.nc-home .nc-feature-icon{margin-bottom:17px}.nc-home .nc-feature h3{font-size:19px}.nc-home .nc-steps{margin-top:33px}.nc-home .nc-step{padding-bottom:10px}.nc-home .nc-step-number{margin-bottom:10px}.nc-home .nc-ribbon-inner{gap:12px}.nc-home .nc-ribbon-item{font-size:10px;gap:8px}.nc-home .nc-ribbon-number{font-size:20px}.nc-home .nc-floating{right:0;top:56px;padding:10px 11px}.nc-home .nc-floating-icon{width:27px;height:27px;font-size:17px}.nc-home .nc-floating strong{font-size:10px}.nc-home .nc-floating small{font-size:9px}.nc-home .nc-planner-body{padding:17px}.nc-home .nc-food-panel{padding:21px}.nc-home .nc-selected-count{font-size:9px}.nc-home .nc-recipe-text{padding:29px}.nc-home .nc-recipe-text h2{font-size:35px}.nc-home .nc-journal{padding:22px}.nc-home .nc-cta{padding:28px}.nc-home .nc-cta h2{font-size:33px}.nc-home .nc-btn{min-height:49px;padding:12px 17px;font-size:13px}}
@media(prefers-reduced-motion:reduce){.nc-home .nc-btn,.nc-home .nc-chip{transition:none}.nc-home .nc-btn:hover{transform:none}}


.nc-home .nc-lifestyle-hero{position:relative;align-self:stretch;min-height:500px;border-radius:24px;overflow:hidden;background:#e5ebdd}
.nc-home .nc-lifestyle-hero>img{width:100%;height:100%;object-fit:cover;position:absolute}
.nc-home .nc-lifestyle-caption{position:absolute;bottom:22px;left:22px;right:22px;padding:18px 22px;border-radius:12px;background:#faf8f2f2;display:grid;gap:3px;color:#153f32}
.nc-home .nc-lifestyle-caption strong{font-family:Georgia,serif;font-size:23px;font-weight:400}
.nc-home .nc-lifestyle-caption span{font-size:13px;color:#53675e}
.nc-home .nc-preference-actions{margin-top:26px}
.nc-home .nc-preference-note{font-size:12px;color:#53675e;margin-top:12px}
.nc-home .nc-product-section{background:#f1f3e9}
.nc-home .nc-future{border-bottom:1px solid #e2e7dc}
.nc-home .nc-future .nc-copy{margin-top:20px;max-width:700px}
.nc-home #nc-supplements{margin-top:30px}
@media(max-width:850px){.nc-home .nc-lifestyle-hero{min-height:420px}.nc-home .nc-product-section .nc-hero-visual{margin-top:16px}}
@media(max-width:580px){.nc-home .nc-lifestyle-hero{min-height:350px}.nc-home .nc-lifestyle-caption{left:14px;right:14px;bottom:14px;padding:15px}.nc-home .nc-lifestyle-caption strong{font-size:21px}.nc-home .nc-hero h1{font-size:46px}}

</style>

<main class="nc-home" id="nc-home">
    <section class="nc-hero" aria-labelledby="nc-hero-title">
        <div class="nc-wrap nc-hero-grid">
            <div>
                <div class="nc-eyebrow"><span class="nc-dot"></span> Nutrition built around your everyday life</div>
                <h1 class="nc-display" id="nc-hero-title">Eat better.<br><em>In a way that fits your life.</em></h1>
                <p class="nc-copy">DietsDNA turns your goals and the foods you enjoy into personalised meal plans. Know what to eat, organise your shopping and follow your progress—with everyday tools you can use independently.</p>
                <div class="nc-actions">
                    <a class="nc-btn nc-btn-primary" href="{{ $startUrl }}">{{ $startLabel }} <span class="nc-arrow" aria-hidden="true">↗</span></a>
                    <a class="nc-btn nc-btn-outline" href="#nc-food-preferences">Explore your food choices</a>
                </div>
                <div class="nc-hero-note"><span><span class="nc-check" aria-hidden="true">✓</span> Personal macro targets</span><span><span class="nc-check" aria-hidden="true">✓</span> Your profile and goals</span><span><span class="nc-check" aria-hidden="true">✓</span> Food you enjoy</span></div>
            </div>
            <div class="nc-hero-visual">
                <div class="nc-planner" aria-label="Illustrative meal planner preview">
                    <div class="nc-planner-top"><span class="nc-mini-brand"><i aria-hidden="true">D</i>DietsDNA</span><span class="nc-mini-pill">Your nutrition profile</span></div>
                    <div class="nc-planner-body">
                        <div class="nc-planner-title"><div><h3>A plan shaped around you.</h3><span class="nc-small">Your preferences, nutrition targets and goals.</span></div><span class="nc-small" aria-hidden="true">↗</span></div>
                        <div class="nc-days" aria-hidden="true"><div class="nc-day">Mon<b>01</b></div><div class="nc-day">Tue<b>02</b></div><div class="nc-day is-active">Wed<b>03</b></div><div class="nc-day">Thu<b>04</b></div><div class="nc-day">Fri<b>05</b></div><div class="nc-day">Sat<b>06</b></div><div class="nc-day">Sun<b>07</b></div></div>
                        <div class="nc-meal-photo"><img src="https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&amp;fit=crop&amp;w=900&amp;q=85" alt="Colourful bowl of fresh vegetables" width="900" height="600" fetchpriority="high"><span class="nc-photo-label">Lunch inspiration</span></div>
                        <div class="nc-featured-meal"><h4>A colourful bowl of good things</h4><div class="nc-meal-meta"><span>Fresh ingredients</span><span>·</span><span>Made your way</span></div></div>
                        <div class="nc-mini-meals"><div class="nc-mini-meal"><span class="nc-food-icon" aria-hidden="true">🥣</span><div><strong>Greek Yogurt, Oats & Berries</strong><small>Breakfast inspiration</small></div></div><div class="nc-mini-meal"><span class="nc-food-icon" aria-hidden="true">🥔</span><div><strong>Beef, Sweet Potato with Red Peppers</strong><small>Dinner inspiration</small></div></div></div>
                    </div>
                </div>
                <div class="nc-floating"><span class="nc-floating-icon" aria-hidden="true">✓</span><div><strong>Built around your preferences</strong><small>Your foods, your goals, your week.</small></div></div>
                <p class="nc-example-caption">Illustrative meal planner. Your own plan appears in your account.</p>
            </div>
        </div>
    </section>

    <div class="nc-ribbon"><div class="nc-wrap nc-ribbon-inner"><div class="nc-ribbon-item"><span class="nc-ribbon-number">01</span> Personal calorie &amp; macro targets</div><div class="nc-ribbon-item"><span class="nc-ribbon-number">02</span> Your profile and goals</div><div class="nc-ribbon-item"><span class="nc-ribbon-number">03</span> Weekly meals and shopping lists</div><div class="nc-ribbon-item"><span class="nc-ribbon-number">04</span> Meals and progress in one place</div></div></div>

    <section class="nc-section" id="nc-features" aria-labelledby="features-title">
        <div class="nc-wrap">
            <div class="nc-section-heading"><div><div class="nc-eyebrow"><span class="nc-dot"></span> A fuller picture. A more personal plan.</div><h2 class="nc-display" id="features-title">Your preferences, targets<br>and meals together.</h2></div><p class="nc-copy">Connect your nutrition targets with practical meal ideas, a weekly plan and a shopping list. Keep your favourites and follow your weight progress in one place.</p></div>
            <div class="nc-cards">
                <article class="nc-feature"><div class="nc-feature-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3"/></svg></div><h3>Personal calorie &amp; macro targets</h3><p>Use your age, height, weight, activity level and goals to calculate personal calorie, protein, carbohydrate and fat targets.</p><span class="nc-feature-tag">Built around your preferences</span></article>
                <article class="nc-feature"><div class="nc-feature-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 4h16v16H4zM8 4v16M4 9h16M12 13h4m-4 3h4"/></svg></div><h3>Diet plans that fit real life</h3><p>Turn your goals and preferences into practical meal plans with familiar ingredients and clear cooking steps.</p><span class="nc-feature-tag">Simple ingredients. Clear steps.</span></article>
                <article class="nc-feature"><div class="nc-feature-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 4v16h16M7 15l4-4 4 2 5-7"/><path d="M16 6h4v4"/></svg></div><h3>Save meals. Track progress.</h3><p>Save meals you enjoy, organise your weekly plan and shopping list, and record weight entries to follow your progress.</p><span class="nc-feature-tag">Your routine, in one place</span></article>
            </div>
        </div>
    </section>

    <section class="nc-section nc-food-section" id="nc-food-preferences" aria-labelledby="nc-food-title">
        <div class="nc-wrap nc-food-grid">
            <div><div class="nc-eyebrow"><span class="nc-dot"></span> Made with your favourites</div><h2 class="nc-display" id="nc-food-title">Good food starts<br>with what <em>you like.</em></h2><p class="nc-copy">Start with foods you actually enjoy. In your account, browse the ingredient groups, mark foods you like and add your dislikes. DietsDNA uses those preferences alongside your goals and nutrition targets to shape your meal ideas, so planning starts with your tastes.</p><div class="nc-food-notes"><span><span class="nc-check" aria-hidden="true">✓</span> Organised lists of everyday ingredients</span><span><span class="nc-check" aria-hidden="true">✓</span> Choices across meat, fish and plant proteins</span><span><span class="nc-check" aria-hidden="true">✓</span> Liked and disliked foods in your preferences</span></div><div class="nc-actions nc-preference-actions"><a class="nc-btn nc-btn-primary" href="{{ $startUrl }}">{{ $startLabel }} <span aria-hidden="true">↗</span></a></div><p class="nc-preference-note">Create your account to save preferences and explore your own plans.</p></div>
            <div class="nc-food-panel">
                <div class="nc-food-panel-title"><strong>What do you enjoy?</strong><span class="nc-selected-count" id="nc-food-count" role="status" aria-live="polite">6 foods selected</span></div>
                @foreach($foodGroups as $group)
                    <div class="nc-food-group"><div class="nc-food-label"><span aria-hidden="true">{{ $group['icon'] }}</span> {{ $group['name'] }}</div><div class="nc-food-chips">
                        @foreach($group['foods'] as $food)
                            <button type="button" class="nc-chip" aria-pressed="{{ in_array($food, ['Broccoli', 'Spinach', 'Chicken', 'Salmon', 'Oats', 'Blueberries']) ? 'true' : 'false' }}">{{ $food }}</button>
                        @endforeach
                    </div></div>
                @endforeach
                <p class="nc-demo-note">Try tapping foods to select or deselect them. This preview shows liked foods only; it is not saved or carried into registration. Set likes and dislikes in your account.</p>
            </div>
        </div>
    </section>

    <section class="nc-section nc-how" id="nc-how-it-works" aria-labelledby="nc-how-title">
        <div class="nc-wrap"><div class="nc-eyebrow"><span class="nc-dot"></span> How it works</div><h2 class="nc-display" id="nc-how-title">From your information<br>to a plan that fits.</h2><div class="nc-steps"><article class="nc-step"><div class="nc-step-number">01</div><h3>Build your profile</h3><p>Share your age, height, weight, activity level and goals to establish your nutrition profile and personal calorie and macro targets.</p></article><article class="nc-step"><div class="nc-step-number">02</div><h3>Set your preferences</h3><p>Choose foods you enjoy and mark your dislikes. Use your preferences to shape the meal ideas you explore.</p></article><article class="nc-step"><div class="nc-step-number">03</div><h3>Get your nutrition plan</h3><p>Generate meal ideas, save your favourites and build your weekly plan. Use your shopping list and log your weight as you go.</p></article></div></div>
    </section>

    <section class="nc-section nc-meals-section" aria-labelledby="nc-meals-title">
        <div class="nc-wrap nc-recipe-grid"><div class="nc-recipe-image"><img src="https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&amp;fit=crop&amp;w=1100&amp;q=85" alt="Fresh vegetables arranged in a colourful bowl" width="1100" height="750" loading="lazy"><span class="nc-recipe-sticker">Fresh ideas for everyday meals</span></div><div class="nc-recipe-text"><div class="nc-eyebrow"><span class="nc-dot"></span> Keep it delicious</div><h2 class="nc-display" id="nc-meals-title">Food to look<br>forward to.</h2><p>Think colourful bowls, comforting breakfasts and straightforward dinners. Meal planning should bring variety to your week, with ingredients that feel familiar.</p><div class="nc-recipe-tags"><span>Everyday ingredients</span><span>Clear instructions</span><span>Flexible preferences</span></div><div class="nc-recipe-example">Food photography is illustrative.</div></div></div>
    </section>
    
<style>
    #nc-progress {
        position: relative;
        isolation: isolate;
        overflow: hidden;
        background: #153f32;
        color: #fff;
    }

    #nc-progress::before {
        content: "";
        position: absolute;
        z-index: -1;
        width: 560px;
        height: 560px;
        right: -240px;
        top: -260px;
        border: 1px solid rgba(219, 238, 185, .12);
        border-radius: 50%;
        box-shadow:
            0 0 0 65px rgba(219, 238, 185, .025),
            0 0 0 130px rgba(219, 238, 185, .025);
        pointer-events: none;
    }

    #nc-progress .nc-progress-grid {
        grid-template-columns: 1fr 1.05fr;
        gap: 72px;
        align-items: center;
    }

    #nc-progress .nc-eyebrow {
        color: #dbeeb9;
    }

    #nc-progress h2 {
        margin-bottom: 24px;
    }

    #nc-progress h2 em {
        color: #dbeeb9;
        font-weight: 400;
    }

    #nc-progress .nc-copy {
        max-width: 470px;
        color: #c5d5c9;
        font-size: 16px;
        line-height: 1.8;
    }

    #nc-progress .nc-progress-points {
        display: grid;
        gap: 14px;
        margin: 27px 0 30px;
        padding: 0;
        list-style: none;
    }

    #nc-progress .nc-progress-points li {
        display: flex;
        align-items: center;
        gap: 11px;
        color: #edf2e8;
        font-size: 13px;
    }

    #nc-progress .nc-progress-points li::before {
        content: "✓";
        display: grid;
        place-items: center;
        flex: 0 0 21px;
        height: 21px;
        border: 1px solid #52725e;
        border-radius: 50%;
        color: #dbeeb9;
        font-size: 11px;
    }

    #nc-progress .nc-progress-button {
        background: #dbeeb9;
        color: #153f32;
    }

    #nc-progress .nc-progress-button:hover {
        background: #edf2e8;
        color: #153f32;
    }

    #nc-progress .nc-progress-preview {
        padding: 30px;
        border: 1px solid #e2e7dc;
        border-radius: 22px;
        background: #faf8f2;
        color: #23382e;
        box-shadow: 0 24px 60px rgba(0, 0, 0, .12);
    }

    #nc-progress .nc-progress-preview-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 18px;
        margin-bottom: 25px;
    }

    #nc-progress .nc-progress-preview-head h3 {
        margin: 0 0 5px;
        color: #153f32;
        font-size: 19px;
        font-weight: 650;
        letter-spacing: -.4px;
    }

    #nc-progress .nc-progress-preview-head p {
        color: #6b786f;
        font-size: 12px;
    }

    #nc-progress .nc-progress-example {
        flex-shrink: 0;
        padding: 5px 10px;
        border: 1px solid #dce3d4;
        border-radius: 99px;
        color: #607058;
        font-size: 10px;
    }

    #nc-progress .nc-progress-journey {
        padding: 23px;
        border: 1px solid #dce5cf;
        border-radius: 15px;
        background: #edf2e4;
    }

    #nc-progress .nc-progress-journey-label {
        color: #52674b;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 1.3px;
        text-transform: uppercase;
    }

    #nc-progress .nc-progress-journey-title {
        margin-top: 9px;
        color: #153f32;
        font-family: Georgia, "Times New Roman", serif;
        font-size: 27px;
        line-height: 1.2;
        letter-spacing: -.7px;
    }

    #nc-progress .nc-progress-stages {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        margin-top: 25px;
    }

    #nc-progress .nc-progress-stage {
        position: relative;
        min-width: 0;
    }

    #nc-progress .nc-progress-stage:not(:last-child)::after {
        content: "";
        position: absolute;
        top: 15px;
        left: 39px;
        right: -4px;
        height: 1px;
        background: #b9cda4;
    }

    #nc-progress .nc-progress-stage-number {
        display: grid;
        place-items: center;
        width: 31px;
        height: 31px;
        margin-bottom: 11px;
        border: 1px solid #b9cda4;
        border-radius: 50%;
        background: #faf8f2;
        color: #153f32;
        font-size: 11px;
        font-weight: 700;
    }

    #nc-progress .nc-progress-stage:last-child .nc-progress-stage-number {
        border-color: #153f32;
        background: #153f32;
        color: #dbeeb9;
    }

    #nc-progress .nc-progress-stage strong {
        display: block;
        color: #153f32;
        font-size: 11px;
        font-weight: 650;
    }

    #nc-progress .nc-progress-tools {
        display: grid;
        gap: 10px;
        margin-top: 19px;
    }

    #nc-progress .nc-progress-tool {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px;
        border: 1px solid #e2e7dc;
        border-radius: 11px;
        background: #fffefb;
    }

    #nc-progress .nc-progress-tool-icon {
        display: grid;
        place-items: center;
        flex: 0 0 38px;
        height: 38px;
        border-radius: 10px;
        background: #eef2e7;
        color: #286449;
    }

    #nc-progress .nc-progress-tool-icon svg {
        width: 20px;
        height: 20px;
        fill: none;
        stroke: currentColor;
        stroke-width: 1.6;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    #nc-progress .nc-progress-tool strong {
        display: block;
        color: #153f32;
        font-size: 12px;
        font-weight: 650;
    }

    #nc-progress .nc-progress-tool small {
        display: block;
        margin-top: 3px;
        color: #6b786f;
        font-size: 11px;
        line-height: 1.5;
    }

    #nc-progress .nc-progress-preview-note {
        margin-top: 17px;
        color: #6b786f;
        font-size: 10px;
        line-height: 1.6;
    }

    @media (max-width: 1100px) {
        #nc-progress .nc-progress-grid {
            gap: 38px;
        }

        #nc-progress .nc-progress-preview {
            padding: 24px;
        }
    }

    @media (max-width: 850px) {
        #nc-progress .nc-progress-grid {
            grid-template-columns: 1fr;
            gap: 35px;
        }

        #nc-progress .nc-copy {
            max-width: 600px;
        }

        #nc-progress .nc-progress-preview {
            width: 100%;
            max-width: 580px;
        }
    }

    @media (max-width: 580px) {
        #nc-progress .nc-progress-preview {
            padding: 20px;
            border-radius: 16px;
        }

        #nc-progress .nc-progress-preview-head {
            gap: 10px;
        }

        #nc-progress .nc-progress-preview-head h3 {
            font-size: 17px;
        }

        #nc-progress .nc-progress-journey {
            padding: 18px;
        }

        #nc-progress .nc-progress-journey-title {
            font-size: 24px;
        }

        #nc-progress .nc-progress-stages {
            gap: 8px;
        }

        #nc-progress .nc-progress-tool {
            padding: 12px;
            gap: 11px;
        }
    }
</style>

<section
    class="nc-section nc-progress-section"
    id="nc-progress"
    aria-labelledby="nc-progress-title"
>
    <div class="nc-wrap nc-progress-grid">
        <div>
            <div class="nc-eyebrow">
                <span class="nc-dot" aria-hidden="true"></span>
                More than a meal plan
            </div>

            <h2 class="nc-display" id="nc-progress-title">
                Small steps.<br>
                <em>Progress that’s yours.</em>
            </h2>

            <p class="nc-copy">
                Eating well takes time to become a routine. Keep your meals,
                weight entries and goals together, so you can see where you
                are and decide what comes next.
            </p>

            <ul class="nc-progress-points">
                <li>Record your weight and review changes over time</li>
                <li>Keep your nutrition goals close at hand</li>
                <li>Build a routine around the foods you enjoy</li>
            </ul>

            <a
                class="nc-btn nc-progress-button"
                href="{{ $startUrl }}"
            >
                {{ $startLabel }}
                <span class="nc-arrow" aria-hidden="true">↗</span>
            </a>
        </div>

        <div class="nc-progress-preview">
            <div class="nc-progress-preview-head">
                <div>
                    <h3>Your journey, in one place.</h3>
                    <p>A little structure for your everyday choices.</p>
                </div>

                <span class="nc-progress-example">Preview</span>
            </div>

            <div class="nc-progress-journey">
                <div class="nc-progress-journey-label">YOUR PLAN. YOUR PROGRESS.</div>

                <div class="nc-progress-journey-title">
                    See how you’re doing.<br>
                    Plan what comes next.
                </div>

                <div class="nc-progress-stages">
                    <div class="nc-progress-stage">
                        <span class="nc-progress-stage-number">01</span>
                        <strong>Set your goals</strong>
                    </div>

                    <div class="nc-progress-stage">
                        <span class="nc-progress-stage-number">02</span>
                        <strong>Plan your meals</strong>
                    </div>

                    <div class="nc-progress-stage">
                        <span class="nc-progress-stage-number">03</span>
                        <strong>Review &amp; adjust</strong>
                    </div>
                </div>
            </div>

            <div class="nc-progress-tools">
                <div class="nc-progress-tool">
                    <span class="nc-progress-tool-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M5 4h14v16H5zM9 4v16M12 9h4m-4 4h4m-4 4h3"/>
                        </svg>
                    </span>
                    <div>
                        <strong>Your weekly plan</strong>
                        <small>Bring your meals and shopping list together.</small>
                    </div>
                </div>

                <div class="nc-progress-tool">
                    <span class="nc-progress-tool-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M4 4v16h16M8 8h2m-2 5h6m-6 4h10"/>
                            <circle cx="18" cy="8" r="2"/>
                        </svg>
                    </span>
                    <div>
                        <strong>Your weight entries</strong>
                        <small>Look back at the records you add over time.</small>
                    </div>
                </div>

                <div class="nc-progress-tool">
                    <span class="nc-progress-tool-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="8"/>
                            <circle cx="12" cy="12" r="4"/>
                            <path d="M12 2v3m0 14v3M2 12h3m14 0h3"/>
                        </svg>
                    </span>
                    <div>
                        <strong>Your personal targets</strong>
                        <small>Review your profile and goals as your needs change.</small>
                    </div>
                </div>
            </div>

            <p class="nc-progress-preview-note">
                Illustrative overview of the planning and progress tools.
                Your own records appear inside your account.
            </p>
        </div>
    </div>
</section>
    
    <section class="nc-section" id="nc-faq" aria-labelledby="nc-faq-title"><div class="nc-wrap nc-faq-grid"><div><div class="nc-eyebrow"><span class="nc-dot"></span> A few things to know</div><h2 class="nc-display" id="nc-faq-title">Your questions,<br>answered.</h2><p class="nc-faq-intro">A closer look at your nutrition profile, meal planning and everyday tools.</p></div><div>@foreach($faqs as $faq)<details class="nc-faq"><summary>{{ $faq['question'] }}</summary><p>{{ $faq['answer'] }}</p></details>@endforeach</div></div></section>
    
        <section class="nc-section" aria-labelledby="nc-life-title"><div class="nc-wrap nc-recipe-grid"><div class="nc-recipe-image"><img src="{{ asset('images/dietsdna-lifestyle-kitchen.jpg') }}" alt="A woman enjoying preparing a fresh salad at home" width="1536" height="1024" loading="lazy" decoding="async"></div><div class="nc-recipe-text"><div class="nc-eyebrow"><span class="nc-dot"></span> Make room for living</div><h2 class="nc-display" id="nc-life-title">Eating well is part of life.<br>Not your whole life.</h2><p>A little structure can make everyday choices easier. Plan meals around your tastes and routine, then get on with the things you enjoy.</p><a class="nc-btn nc-btn-primary" href="{{ $startUrl }}">{{ $startLabel }} <span aria-hidden="true">↗</span></a></div></div></section>
    


    <section class="nc-cta-section" aria-labelledby="nc-cta-title"><div class="nc-wrap"><div class="nc-cta"><div><h2 class="nc-display" id="nc-cta-title">Make your favourite foods<br>part of the plan.</h2><p>Personal targets, meal ideas, weekly plans and shopping lists in one place.</p></div><a class="nc-btn nc-btn-primary" href="{{ $startUrl }}">{{ $startLabel }} <span class="nc-arrow" aria-hidden="true">↗</span></a></div><p class="nc-launch-note">Page visuals and meal examples are illustrative. Food selections in the preview are not saved to your account.</p></div></section>
</main>

<script>
(function () {
    'use strict';
    var root = document.getElementById('nc-home');
    if (!root) return;
    var chips = root.querySelectorAll('.nc-chip');
    var counter = root.querySelector('#nc-food-count');
    function updateCount() {
        var count = root.querySelectorAll('.nc-chip[aria-pressed="true"]').length;
        counter.textContent = count + (count === 1 ? ' food selected' : ' foods selected');
    }
    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            chip.setAttribute('aria-pressed', chip.getAttribute('aria-pressed') === 'true' ? 'false' : 'true');
            updateCount();
        });
    });
    updateCount();
    root.querySelectorAll('a[href^="#nc-"]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            var target = document.getElementById(link.getAttribute('href').slice(1));
            if (!target) return;
            event.preventDefault();
            var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            target.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' });
            target.setAttribute('tabindex', '-1');
            target.focus({ preventScroll: true });
        });
    });

}());
</script>

@include('partials.footer')
