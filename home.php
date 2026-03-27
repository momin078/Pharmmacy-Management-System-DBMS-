<?php require_once "config.php"; if (session_status()===PHP_SESSION_NONE) session_start(); ?>
<?php include "header.php"; ?>
<?php include "navbar.php"; ?>

<style>
html,body{height:100%;margin:0;padding:0;display:flex;flex-direction:column}
main{flex:1;display:flex;flex-direction:column}
.slider{flex:1;width:100%;overflow:hidden;margin:0;border-radius:0;background:#f4f4f4;position:relative}
.slides{display:flex;transition:transform .6s ease-in-out;will-change:transform;height:100%}
.slide{min-width:100%;height:100%;display:flex;justify-content:center;align-items:center}
.slide img{max-width:100%;max-height:100%;object-fit:contain;display:block}
.prev,.next{position:absolute;top:50%;transform:translateY(-50%);background:rgba(0,0,0,.5);color:#fff;padding:10px;border:none;cursor:pointer;border-radius:50%;z-index:2;line-height:1}
.prev{left:20px}.next{right:20px}
.dots{position:absolute;bottom:15px;width:100%;text-align:center}
.dot{height:12px;width:12px;margin:0 4px;display:inline-block;background:#bbb;border-radius:50%;cursor:pointer}
.dot.active{background:#009688}
</style>

<main>
  <div class="slider" id="slider" aria-roledescription="carousel">
    <div class="slides" id="slides">
      <div class="slide"><img src="https://www.lazzpharma.com/Content/ImageData/Banner/Orginal/6155262a-31d1-4546-8d86-352a0036762f/banner.webp" alt="Promotional Banner 1" loading="lazy"></div>
      <div class="slide"><img src="https://www.lazzpharma.com/Content/ImageData/Banner/Orginal/f57696ed-c453-4373-95b9-dd72b7b03719/banner.webp" alt="Promotional Banner 2" loading="lazy"></div>
      <div class="slide"><img src="https://www.lazzpharma.com/Content/ImageData/Banner/Orginal/fe4288bc-33e2-4327-a47e-fd796aadf454/banner.webp" alt="Promotional Banner 3" loading="lazy"></div>
    </div>
    <button class="prev" type="button" aria-label="Previous slide" onclick="moveSlide(-1)">&#10094;</button>
    <button class="next" type="button" aria-label="Next slide" onclick="moveSlide(1)">&#10095;</button>
    <div class="dots" id="dots" aria-label="Slide indicators"></div>
  </div>
</main>

<script>
(function(){
  let currentIndex=0;
  const slidesEl=document.getElementById("slides");
  const slideCount=slidesEl.children.length;
  const dotsContainer=document.getElementById("dots");
  const slider=document.getElementById("slider");
  for(let i=0;i<slideCount;i++){
    const dot=document.createElement("span");
    dot.className="dot"; dot.setAttribute("role","button");
    dot.setAttribute("aria-label","Go to slide "+(i+1));
    dot.addEventListener("click", function(){ showSlide(i); resetAuto();});
    dotsContainer.appendChild(dot);
  }
  const dots=document.querySelectorAll(".dot");
  function showSlide(index){
    if(index>=slideCount) index=0;
    if(index<0) index=slideCount-1;
    slidesEl.style.transform="translateX("+(-index*100)+"%)";
    currentIndex=index; dots.forEach(d=>d.classList.remove("active"));
    if(dots[index]) dots[index].classList.add("active");
  }
  window.moveSlide=function(step){ showSlide(currentIndex+step); resetAuto(); };
  let intervalMs=5000, timerId=null;
  function startAuto(){ if(timerId) return; timerId=setInterval(()=>showSlide(currentIndex+1), intervalMs); }
  function stopAuto(){ if(timerId){ clearInterval(timerId); timerId=null; } }
  function resetAuto(){ stopAuto(); startAuto(); }
  slider.addEventListener("mouseenter", stopAuto);
  slider.addEventListener("mouseleave", startAuto);
  document.addEventListener("visibilitychange", ()=>{ if(document.hidden) stopAuto(); else startAuto(); });
  slider.setAttribute("tabindex","0");
  slider.addEventListener("keydown",(e)=>{ if(e.key==="ArrowLeft") moveSlide(-1); if(e.key==="ArrowRight") moveSlide(1); });
  showSlide(0); startAuto();
})();
</script>

<?php include "footer.php"; ?>
