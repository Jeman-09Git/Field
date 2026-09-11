function Change2(){
    reset();
     document.body.style.backgroundColor= "rgb(246, 64, 76)"; 
     document.body.style.color="white";
      const links = document.querySelectorAll(".link");
    links.forEach( a => {
        a.style.color= "red";
    });
    const bar = document.getElementById('bar');
    bar.style.backgroundColor= "pink";
    const div=document.getElementById('div1');
    div.style.backgroundColor= "white";
    div.style.color= "black";
    const par = document.getElementById('par');
    par.style.backgroundColor= "rgb(246, 64, 76)";
    par.style.color= "white";

}
function None(){
    reset();
    document.getElementById("Main").disabled= true;
     document.body.style.backgroundColor=""; 
     document.body.style.color="";
      document.body.style.fontFamily="";
      const headings = document.querySelectorAll(".he1");
    headings.forEach( h1 => {
        h1.style.color= "";
    });
    document.getElementById('bar').style.margin= "none";
    document.getElementById('side').style.color= "black";

}
function Change4(){
    reset();
    document.body.style.backgroundColor="Black"; 
    document.body.style.color="green";
    const headings = document.querySelectorAll(".he1");
    headings.forEach( h1 => {
        h1.style.color= "lightgreen";
    });
     const links = document.querySelectorAll(".link");
    links.forEach( a => {
        a.style.color= "white";
    });
     const link = document.querySelectorAll(".link2");
    link.forEach( a => {
        a.style.color= "green";
        a.style.transitionBehavior= "white 0.3s ease";
    });


    document.body.style.fontFamily= "Courier New, Courier, monospace";
    const bar = document.getElementById('bar');
    bar.style.width= "300px";
    bar.style.float= "right";
    bar.style.padding= "10px";
    bar.style.color= "white";
    bar.style.border= "2px solid white";
    bar.style.top= "15px";
    bar.style.right= "0px";
    bar.style.position= "fixed";
    document.getElementById('list').style.transitionBehavior= "ease";
    document.getElementById('list').style.transitionDuration= "0.5s";

    const par = document.getElementById('par');
    par.style.border= "2px solid white";
    par.style.color= "white";
    par.style.width= "1500px";
    par.style.height= "100px";
     par.style.textAlign= "center";



} 
 
function Change3(){
    reset();
    document.body.style.backgroundColor="white"; 
    document.body.style.color="black";
    const headings = document.querySelectorAll(".he1");
    headings.forEach( h1 => {
        h1.style.color= "green";
    });
    document.body.style.fontFamily= "Verdana";
    document.getElementById('side').style.color= "green";
    document.getElementById('bar').style.backgroundColor= "lightgrey";
    document.getElementById('bar').style.margin="30px";
    document.getElementById('bar').style.width= "89%";
    document.getElementById('par').style.fontSize= "10px"
    document.getElementById('bar').style.height= "130px";
    document.getElementById('par').style.textAlign= "center";
     const bar = document.getElementById('bar');
    bar.style.border= "none";
    bar.style.float= "none";

}

function Change(){
    reset();
    document.body.style.fontFamily= "Times New Roman, Times, serif";
    document.body.style.backgroundColor= "white";
    document.body.style.color= "black";

    const head=document.getElementById('head');
    head.style.backgroundColor= "green";
    head.style.color= "white";

    const bar = document.getElementById('bar');
    bar.style.width= "220px";
    bar.style.float= "right";
    bar.style.padding= "15px";
    bar.style.color= "white";
    bar.style.border= "2px solid white";;
    bar.style.right= "0px";
    bar.style.background= "linear-gradient(to bottom, #4a90d9, #2c5f9e)"
    bar.style.position= "fixed";
    bar.style.backgroundColor= "aqua";
    bar.style.height= "800px";
    bar.style.top= "105px";

     const div=document.getElementById('div1');
    div.style.float= "right";
    const lisTs= document.getElementById('list');
    lisTs.style.float= "left";


}
function reset(){
     const bar = document.getElementById('bar');
    bar.style.width= "";
    bar.style.float= "none";
    bar.style.padding= "";
    bar.style.color= "";
    bar.style.border= "none";
    bar.style.top= "";
    bar.style.left= "";
    bar.style.right= "";
    bar.style.position= "static";
    bar.style.height= "";
    bar.style.margin= "";
    bar.style.backgroundColor= "";
    bar.style.background= "";
    document.getElementById('par').style.fontSize= "";
     document.getElementById('par').style.textAlign= "";
      const links = document.querySelectorAll(".link");
    links.forEach( a => {
        a.style.color= "";
    });
     const link = document.querySelectorAll(".link2");
    link.forEach( a => {
        a.style.color= "";
        a.style.transitionBehavior= "";
    });
 const div=document.getElementById('div1');
    div.style.backgroundColor= "";
    div.style.color= "";
    const headings = document.querySelectorAll(".he1");
    headings.forEach( h1 => {
        h1.style.color= "";
    });
 document.getElementById('side').style.color= "";
 const par = document.getElementById('par');
    par.style.backgroundColor= "";
    par.style.color= "";
 const head=document.getElementById('head');
    head.style.backgroundColor= "";
    head.style.color= "";
    par.style.border= "";
    par.style.color= "";
    par.style.width= "";
    par.style.height= "";
    par.style.textAlign= "";

}