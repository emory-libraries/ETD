function addStreet(id, classname){
    //Find correct spot to add new street
    var streets = document.getElementById(id);

    //Create title
    var titleSpan = document.createElement('span');
    titleSpan.setAttribute("class", classname);
    titleSpan.innerHTML = "street";
    streets.appendChild(titleSpan);
    streets.appendChild(document.createElement("br"));

    //Create input
    var input = document.createElement('input');
    input.setAttribute("type", "text");
    input.setAttribute('id', id.substr(0, id.length-1));
    input.setAttribute("name", id.substr(0, id.length-1)+"[]");
    
    var div = document.createElement("div");
    div.appendChild(titleSpan);
    div.appendChild(document.createElement("br"));
    div.appendChild(input);
    div.appendChild(document.createElement("br"));
    div.appendChild(document.createElement("br"));
    streets.appendChild(div);


}

function removeStreet(id){
    var streets = document.getElementById(id);

    if(streets.childNodes.length > 3){
        streets.removeChild(streets.lastChild);
        streets.removeChild(streets.lastChild);
    }
}