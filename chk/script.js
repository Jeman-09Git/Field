function Change2(){
    reset();
     document.body.style.backgroundColor="red"; 
     document.body.style.color="white";

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
    document.body.style.fontFamily= "Courier New, Courier, monospace";
    const bar = document.getElementById('bar');
    bar.style.width= "300px";
    bar.style.float= "right";
    bar.style.padding= "10px";
    bar.style.color= "white";
    bar.style.border= "2px solid white";
    bar.style.top= "10px";
    bar.style.right= "50%";
    bar.style.position= "fixed";
    document.getElementById('list').style.transition= "0.3s ease";
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
    document.getElementById('bar').style.width= "900px";
    document.getElementById('par').style.fontSize= "10px"
    document.getElementById('bar').style.height= "130px";
     const bar = document.getElementById('bar');
    bar.style.border= "none";
    bar.style.float= "none";
}

function Change(){
reset();

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
}