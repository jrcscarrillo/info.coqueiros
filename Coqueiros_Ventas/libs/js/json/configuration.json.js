Bizagi.AppModel = {"personalized":false,"userLogoName":"\\libs\\img\\biz-ex-logo.png","bizagiUrl":"http://www.bizagi.com","productName":"Bizagi Modeler","modelName":"Coqueiros_Ventas","publishDate":"3/12/2016 8:52:33 AM","pages":[{"id":"a71f1b27-8b5b-4a76-8fd9-6dbe27e5bbbd","name":"Ventas","version":"1.0","author":"Juan","image":"files\\diagrams\\Ventas.png","isSubprocessPage":false,"elements":[{"id":"16c176f9-652f-4058-afdc-8b038248abfb","name":"Aurora_Ventas","elementImage":"files\\bpmnElements\\Participant.png","imageBounds":{"points":[{"x":20.0,"y":20.0}],"radius":0.0,"height":530.0,"width":50.0,"shape":"rect"},"elementType":"Participant","properties":[],"pageElements":[{"id":"8def1001-776c-4cb4-af49-b5a7d91b5f40","name":"Event","elementImage":"files\\bpmnElements\\NoneStart.png","imageBounds":{"points":[{"x":134.0,"y":91.0}],"radius":15.0,"height":30.0,"width":30.0,"shape":"circle"},"elementType":"NoneStart"},{"id":"51ed0bba-1b9c-42bb-b16b-9c1d92b60b5b","name":"Gateway","elementImage":"files\\bpmnElements\\ExclusiveGateway.png","imageBounds":{"points":[{"x":330.0,"y":86.0}],"radius":0.0,"height":40.0,"width":40.0,"shape":"poly"},"elementType":"ExclusiveGateway","properties":[],"pageElements":[{"name":"Cliente","elementType":"SequenceFlow","properties":[]},{"name":"Servidor","elementType":"SequenceFlow","properties":[]}]},{"id":"de7114de-8bc0-456c-a090-c78a54a979b4","name":"Tipo Dispositivo","elementImage":"files\\bpmnElements\\AbstractTask.png","imageBounds":{"points":[{"x":305.0,"y":204.0}],"radius":0.0,"height":60.0,"width":90.0,"shape":"rect"},"elementType":"AbstractTask","properties":[]},{"id":"6e7271e5-19f8-4b02-b348-12b5580ea830","name":"Gateway","elementImage":"files\\bpmnElements\\ExclusiveGateway.png","imageBounds":{"points":[{"x":330.0,"y":321.0}],"radius":0.0,"height":40.0,"width":40.0,"shape":"poly"},"elementType":"ExclusiveGateway","properties":[],"pageElements":[{"name":"WebSite","elementType":"SequenceFlow","properties":[]},{"name":"Tabletas","elementType":"SequenceFlow","properties":[]}]},{"id":"39429879-b944-4ca7-8b28-813e8a9d1005","name":"Servicios Moviles","elementImage":"files\\bpmnElements\\SubProcess.png","imageBounds":{"points":[{"x":493.0,"y":311.0}],"radius":0.0,"height":60.0,"width":90.0,"shape":"rect"},"isPublished":true,"elementType":"SubProcess","properties":[]},{"id":"c8fc153d-b04f-497d-931f-4243c0635e4c","name":"Event","elementImage":"files\\bpmnElements\\NoneEnd.png","imageBounds":{"points":[{"x":523.0,"y":465.0}],"radius":15.0,"height":30.0,"width":30.0,"shape":"circle"},"elementType":"NoneEnd"},{"id":"bf0a0b9f-ab06-4f06-a533-7852aeae119d","name":"Servicios Web","elementImage":"files\\bpmnElements\\SubProcess.png","imageBounds":{"points":[{"x":305.0,"y":450.0}],"radius":0.0,"height":60.0,"width":90.0,"shape":"rect"},"isPublished":true,"elementType":"SubProcess","properties":[]},{"id":"54958e71-6bf0-4ec2-9703-f0ca350ea51e","name":"Tareas Servidor","elementImage":"files\\bpmnElements\\SubProcess.png","imageBounds":{"points":[{"x":490.0,"y":76.0}],"radius":0.0,"height":60.0,"width":90.0,"shape":"rect"},"isPublished":true,"elementType":"SubProcess","properties":[]},{"id":"5fde4f83-45df-4f80-9a03-dd2e1c9e69f3","name":"Event","elementImage":"files\\bpmnElements\\NoneEnd.png","imageBounds":{"points":[{"x":520.0,"y":225.0}],"radius":15.0,"height":30.0,"width":30.0,"shape":"circle"},"elementType":"NoneEnd"}]}],"subPages":[{"id":"54958e71-6bf0-4ec2-9703-f0ca350ea51e","name":"Tareas Servidor","image":"files\\diagrams\\Tareas_Servidor.png","isSubprocessPage":true,"elements":[{"id":"36a09cd3-740a-4bc2-88fa-55ae2daee9c0","name":"Event","elementImage":"files\\bpmnElements\\NoneStart.png","imageBounds":{"points":[{"x":20.0,"y":159.0}],"radius":15.0,"height":30.0,"width":30.0,"shape":"circle"},"elementType":"NoneStart"},{"id":"b502017f-287e-4470-bca3-a5c86583f5b2","name":"QuickBooks","elementImage":"files\\bpmnElements\\AbstractTask.png","imageBounds":{"points":[{"x":198.0,"y":144.0}],"radius":0.0,"height":60.0,"width":90.0,"shape":"rect"},"elementType":"AbstractTask","performers":[],"properties":[]},{"id":"938e3318-6aaf-4591-a14f-651b666d4d9a","name":"Event","elementImage":"files\\bpmnElements\\NoneEnd.png","imageBounds":{"points":[{"x":418.0,"y":159.0}],"radius":15.0,"height":30.0,"width":30.0,"shape":"circle"},"elementType":"NoneEnd"},{"id":"810e8765-93b9-4fbc-b60e-2ac7976bdf29","name":"Web Connector","description":"<p style=\"text-align:left;text-indent:0pt;margin:0pt 0pt 0pt 0pt;\"><span style=\"color:#000000;background-color:transparent;font-family:Segoe UI;font-size:8pt;font-weight:normal;font-style:normal;\">En este proceso el administrador conoce todas las herramientas para procesar los requerimientos diarios de sincronizacion de datos, como registrar nuevos usuarios con sus respectivas aplicaciones, asi como eliminar usuarios y aplicaciones. </span></p>","elementImage":"files\\bpmnElements\\SubProcess.png","imageBounds":{"points":[{"x":198.0,"y":20.0}],"radius":0.0,"height":60.0,"width":90.0,"shape":"rect"},"isPublished":true,"elementType":"SubProcess","properties":[]},{"id":"1eb4f5e5-3481-4ba3-bdf7-a79cc104280e","name":"Event","elementImage":"files\\bpmnElements\\NoneEnd.png","imageBounds":{"points":[{"x":409.0,"y":35.0}],"radius":15.0,"height":30.0,"width":30.0,"shape":"circle"},"elementType":"NoneEnd"},{"id":"248d1c49-c706-4263-813b-14ab69ed6794","name":"Event","elementImage":"files\\bpmnElements\\NoneStart.png","imageBounds":{"points":[{"x":25.0,"y":35.0}],"radius":15.0,"height":30.0,"width":30.0,"shape":"circle"},"elementType":"NoneStart"}],"parentRef":"a71f1b27-8b5b-4a76-8fd9-6dbe27e5bbbd"},{"id":"810e8765-93b9-4fbc-b60e-2ac7976bdf29","name":"Web Connector","description":"<p style=\"text-align:left;text-indent:0pt;margin:0pt 0pt 0pt 0pt;\"><span style=\"color:#000000;background-color:transparent;font-family:Segoe UI;font-size:8pt;font-weight:normal;font-style:normal;\">En este proceso el administrador conoce todas las herramientas para procesar los requerimientos diarios de sincronizacion de datos, como registrar nuevos usuarios con sus respectivas aplicaciones, asi como eliminar usuarios y aplicaciones. </span></p>","image":"files\\diagrams\\Web_Connector.png","isSubprocessPage":true,"elements":[{"id":"f118ca8d-9881-40b5-a529-7e361cbc4b68","name":"Event","elementImage":"files\\bpmnElements\\NoneStart.png","imageBounds":{"points":[{"x":20.0,"y":35.0}],"radius":15.0,"height":30.0,"width":30.0,"shape":"circle"},"elementType":"NoneStart"},{"id":"7a362c6d-33c5-41fd-9b3c-8aaffc9a762f","name":"Integrar QB con otras Aplicaciones","description":"<p style=\"text-align:left;text-indent:0pt;margin:0pt 0pt 0pt 0pt;\"><span style=\"color:#000000;background-color:transparent;font-family:Segoe UI;font-size:8pt;font-weight:normal;font-style:normal;\">Para integrar Quickbooks con otras aplicaciones es necesario contar con una herramienta que permita accesar a la base de datos, esta herramienta es conocida como QB SDK. La herramienta se instala junto con QB (servidor), parte de esta herramienta es Web Connector, esto permite ejecutar procesos que leen, graban, o actualizan datos en Quickbooks desde dispositivos moviles o sitios en internet.</span></p>","elementImage":"files\\bpmnElements\\AbstractTask.png","imageBounds":{"points":[{"x":196.0,"y":20.0}],"radius":0.0,"height":60.0,"width":90.0,"shape":"rect"},"elementType":"AbstractTask","performers":[{"name":"AdminServer","value":"83e7d527-8772-4325-b58e-d0aff78f99aa","type":"resource","pageRef":"Resources"}],"properties":[],"presentationAction":{"value":"files\\attachments\\qbsdk.jpg","type":"image"}},{"id":"be6bb2f9-1c14-486d-9bf6-885b46f10466","name":"Funcionamento del Web Connector","description":"<p style=\"text-align:left;text-indent:0pt;margin:0pt 0pt 0pt 0pt;\"><span style=\"color:#000000;background-color:transparent;font-family:Segoe UI;font-size:8pt;font-weight:normal;font-style:normal;\">En el documento adjunto, se encuentra la explicacion de como utilizar Web Connector</span></p>","elementImage":"files\\bpmnElements\\AbstractTask.png","imageBounds":{"points":[{"x":186.0,"y":118.0}],"radius":0.0,"height":60.0,"width":110.0,"shape":"rect"},"elementType":"AbstractTask","performers":[{"name":"AdminServer","value":"83e7d527-8772-4325-b58e-d0aff78f99aa","type":"resource","pageRef":"Resources"}],"properties":[],"presentationAction":{"value":"files\\attachments\\Web_Connector.pdf","type":"url","urlText":"Web_Connector.pdf"}},{"id":"3f06040e-c820-4dc4-8d07-9c07d192f120","name":"Event","elementImage":"files\\bpmnElements\\NoneEnd.png","imageBounds":{"points":[{"x":372.0,"y":133.0}],"radius":15.0,"height":30.0,"width":30.0,"shape":"circle"},"elementType":"NoneEnd"}],"parentRef":"54958e71-6bf0-4ec2-9703-f0ca350ea51e"},{"id":"39429879-b944-4ca7-8b28-813e8a9d1005","name":"Servicios Moviles","image":"files\\diagrams\\Servicios_Moviles.png","isSubprocessPage":true,"elements":[{"id":"a07c1dbf-7ae7-4a14-b272-321e69bd887e","name":"Inicio del Dia","elementImage":"files\\bpmnElements\\NoneStart.png","imageBounds":{"points":[{"x":44.0,"y":35.0}],"radius":15.0,"height":30.0,"width":30.0,"shape":"circle"},"elementType":"NoneStart"},{"id":"68df5e9d-5ed3-4a89-afad-ad38a53e739b","name":"Login de la Empresa","description":"<p style=\"text-align:left;text-indent:0pt;margin:0pt 0pt 0pt 0pt;\"><span style=\"color:#000000;background-color:transparent;font-family:Segoe UI;font-size:8pt;font-weight:normal;font-style:normal;\">Todas las empresas que utilizan este servicio tienen un direccion internet con certificado de seguridad. Una vez aprobado el ingreso al website de la empresa (es decir a los recursos informaticos contratados), el dispositivo esta listo para trabajar en este ambiente.</span></p>","elementImage":"files\\bpmnElements\\SubProcess.png","imageBounds":{"points":[{"x":211.0,"y":20.0}],"radius":0.0,"height":60.0,"width":90.0,"shape":"rect"},"elementType":"SubProcess","properties":[]},{"id":"6e6849b3-c823-475a-a7e0-5668a9857a37","name":"Login del Usuario","description":"<p style=\"text-align:left;text-indent:0pt;margin:0pt 0pt 0pt 0pt;\"><span style=\"color:#000000;background-color:transparent;font-family:Segoe UI;font-size:8pt;font-weight:normal;font-style:normal;\">El usuario ingresa a la aplicacion, y se produce la verificacion de sus credenciales en el website empresarial. Existiran dos pasos previos. El primero el ingreso de los datos por primera vez, y la aprobacion de su identificacion y clave de seguridad por el administrador del website empresarial.</span></p>","elementImage":"files\\bpmnElements\\SubProcess.png","imageBounds":{"points":[{"x":211.0,"y":131.0}],"radius":0.0,"height":60.0,"width":90.0,"shape":"rect"},"elementType":"SubProcess","properties":[]},{"id":"5e339e21-75f4-42b6-9de0-bce091f5eeb6","name":"Gateway","elementImage":"files\\bpmnElements\\ExclusiveGateway.png","imageBounds":{"points":[{"x":236.0,"y":249.0}],"radius":0.0,"height":40.0,"width":40.0,"shape":"poly"},"elementType":"ExclusiveGateway","properties":[],"pageElements":[{"name":"Control de Datos","elementType":"SequenceFlow","properties":[]},{"name":"Ingreso de Datos","elementType":"SequenceFlow","properties":[]}]},{"id":"276354c5-df96-44ad-9c7c-45086155d2a2","name":"Ingreso de Datos","description":"<p style=\"text-align:left;text-indent:0pt;margin:0pt 0pt 0pt 0pt;\"><span style=\"color:#000000;background-color:transparent;font-family:Segoe UI;font-size:8pt;font-weight:normal;font-style:normal;\">Se ingresan los datos y se comparan con la base de datos de la empresa. Se le asigna un comprobante y se envia un correo electronico al nuevo usuario y al adminstrador del website.</span></p>","elementImage":"files\\bpmnElements\\SubProcess.png","imageBounds":{"points":[{"x":387.0,"y":239.0}],"radius":0.0,"height":60.0,"width":90.0,"shape":"rect"},"elementType":"SubProcess","properties":[]},{"id":"1e534639-6a09-45b2-ac19-5a50dfc1b032","name":"En espera","description":"<p style=\"text-align:left;text-indent:0pt;margin:0pt 0pt 0pt 0pt;\"><span style=\"color:#000000;background-color:transparent;font-family:Segoe UI;font-size:8pt;font-weight:normal;font-style:normal;\">Una vez realizado el proceso de ingreso de datos, verificacion, y control del estado de sincronizacion, se podra continuar con la aplicacion.</span></p>","elementImage":"files\\bpmnElements\\SignalEnd.png","imageBounds":{"points":[{"x":417.0,"y":369.0}],"radius":15.0,"height":30.0,"width":30.0,"shape":"circle"},"elementType":"SignalEnd"},{"id":"349ba4d2-bf39-41bc-9886-8a98511c8afa","name":"Control de Datos","description":"<p style=\"text-align:left;text-indent:0pt;margin:0pt 0pt 0pt 0pt;\"><span style=\"color:#000000;background-color:transparent;font-family:Segoe UI;font-size:8pt;font-weight:normal;font-style:normal;\">Este proceso reisa que los datos del dispositivo movil se encuentren actualizados, si no se encuentran actualizados, se procede a la sincronizacion de los datos entre las bases de datos empresariales a la base de datos del dispositivo movil</span></p>","elementImage":"files\\bpmnElements\\SubProcess.png","imageBounds":{"points":[{"x":211.0,"y":354.0}],"radius":0.0,"height":60.0,"width":90.0,"shape":"rect"},"elementType":"SubProcess","properties":[]}],"parentRef":"a71f1b27-8b5b-4a76-8fd9-6dbe27e5bbbd"},{"id":"bf0a0b9f-ab06-4f06-a533-7852aeae119d","name":"Servicios Web","image":"files\\diagrams\\Servicios_Web.png","isSubprocessPage":true,"elements":[{"id":"93133059-b8c7-4720-aee2-b5f884d3f838","name":"Inicio del Dia","description":"<p style=\"text-align:left;text-indent:0pt;margin:0pt 0pt 0pt 0pt;\"><span style=\"color:#000000;background-color:transparent;font-family:Segoe UI;font-size:8pt;font-weight:normal;font-style:normal;\">Al inicio de la jornada el administrador del website empresarial debera realizar algunas tareas antes de iniciar las operaciones normales.</span></p>","elementImage":"files\\bpmnElements\\NoneStart.png","imageBounds":{"points":[{"x":44.0,"y":35.0}],"radius":15.0,"height":30.0,"width":30.0,"shape":"circle"},"elementType":"NoneStart"},{"id":"d215b6cf-b999-43a5-8e86-aa6774cdb7bd","name":"Login del Usuario","description":"<p style=\"text-align:left;text-indent:0pt;margin:0pt 0pt 0pt 0pt;\"><span style=\"color:#000000;background-color:transparent;font-family:Segoe UI;font-size:8pt;font-weight:normal;font-style:normal;\">Ingresar la identificacion y clave de seguridad. La identificacion y clave de seguridad son manejadas por el proveedor de la aplicacion, y depende del numero de licencias que adquieran. </span></p>","elementImage":"files\\bpmnElements\\SubProcess.png","imageBounds":{"points":[{"x":199.0,"y":20.0}],"radius":0.0,"height":60.0,"width":90.0,"shape":"rect"},"elementType":"SubProcess","properties":[]},{"id":"354e2f7a-a18c-4952-bfd8-cf1bf129e9a1","name":"Gateway","elementImage":"files\\bpmnElements\\ExclusiveGateway.png","imageBounds":{"points":[{"x":224.0,"y":127.0}],"radius":0.0,"height":40.0,"width":40.0,"shape":"poly"},"elementType":"ExclusiveGateway","properties":[],"pageElements":[{"name":"Asignacion de Usuario","elementType":"SequenceFlow","properties":[]},{"name":"WebSite Empresarial","elementType":"SequenceFlow","properties":[]}]},{"id":"76175745-c75e-445d-80fc-26f643f3f4b7","name":"WebSite Empresarial","description":"<p style=\"text-align:left;text-indent:0pt;margin:0pt 0pt 0pt 0pt;\"><span style=\"color:#000000;background-color:transparent;font-family:Segoe UI;font-size:8pt;font-weight:normal;font-style:normal;\">Este website es un completo adminstrador de todas las tareas que se realizan entre, los dispositivos moviles, la base de datos empresarial, y el SRI.</span></p>","elementImage":"files\\bpmnElements\\AbstractTask.png","imageBounds":{"points":[{"x":199.0,"y":214.0}],"radius":0.0,"height":60.0,"width":90.0,"shape":"rect"},"elementType":"AbstractTask","performers":[],"properties":[]},{"id":"af2b2507-58a9-4e09-b6fb-ee8c45e833a0","name":"En espera","description":"<p style=\"text-align:left;text-indent:0pt;margin:0pt 0pt 0pt 0pt;\"><span style=\"color:#000000;background-color:transparent;font-family:Segoe UI;font-size:8pt;font-weight:normal;font-style:normal;\">Concluidos los procesos de registro del administrador, se procedera a utilizar todas las funciones del website</span></p>","elementImage":"files\\bpmnElements\\SignalEnd.png","imageBounds":{"points":[{"x":399.0,"y":229.0}],"radius":15.0,"height":30.0,"width":30.0,"shape":"circle"},"elementType":"SignalEnd"},{"id":"a6b53a23-82fc-44ed-8cd6-f5fa2e28848c","name":"Asignacion de Usuario","description":"<p style=\"text-align:left;text-indent:0pt;margin:0pt 0pt 0pt 0pt;\"><span style=\"color:#000000;background-color:transparent;font-family:Segoe UI;font-size:8pt;font-weight:normal;font-style:normal;\">Por primera vez se conecta al server de Carrillos Team y se le asigna una clave de seguridad al usuario que haya seleccionado, y se envia un correo electronico al responsable (Gerente de la Empresa) y al nuevo usuario.</span></p>","elementImage":"files\\bpmnElements\\SubProcess.png","imageBounds":{"points":[{"x":369.0,"y":117.0}],"radius":0.0,"height":60.0,"width":90.0,"shape":"rect"},"elementType":"SubProcess","properties":[]}],"parentRef":"a71f1b27-8b5b-4a76-8fd9-6dbe27e5bbbd"}]}],"texts":{"tableOfContents":"Table of Contents","pageNumber":"Page","pageNumberLabelOf":"of","version":"Version","author":"Author","description":"Description","mainPool":"Main Process","mainPoolDescription":"Main Process Description","processDiagrams":"Process Diagrams","processElements":"Process Elements","elements":"Process Elements","defaultElementName":"Element","performers":"Performers","connectors":"Connectors","connector":"Connector","home":"Home","search":"Search","goToParentProcess":"<< Go to Parent Process","visitBizagi":"Visit bizagi.com","contains":"Contains {0} Sub-Processes","showAll":"Show all","fullScreen":"Full screen","zoomIn":"Zoom In","zoomOut":"Zoom Out","close":"Close","menu":"Menu: ","errorPage":"Error when visualizing page","process":"Process","subProcess":"Published Sub-Processes","contain":"Contains","checkAttributes":"Check attributes","checkOverview":"Check overview","unavailableResource":"Unavailable resource","localResource":"Resource can be accessed locally","performer":"Performer","linktoimage":"Link to Image","presentationAction":"Presentation Actions","searchGlobal":"Search all","searchLocal":"Search in this process","searchResults":"No Results Found","titlePage":"Start","emptyElement":"This element has not yet been documented","unsupported":"Your browser does not support content displayed by this page. <br> We recommend you upgrading your browser.","details":"Details","expand":"Expand","mainPoolProperties":"Main Process properties","cannotVisualize":"The page cannot be displayed","resourceNotFound":"The requested resource was not found:","applyTheme":"Applying new theme"},"resourcePage":{"id":"Resources","name":"Resources","isSubprocessPage":false,"elements":[{"id":"83e7d527-8772-4325-b58e-d0aff78f99aa","name":"AdminServer","description":"El administrador del servidor podra acceder al web connector para adicionar o eliminar procesos que se ejecutan por un controlador de tiempo.","rol":"Role"}]},"searchMap":[{"containerId":"a71f1b27-8b5b-4a76-8fd9-6dbe27e5bbbd","containerName":"Ventas","isSubprocess":false,"elements":[{"id":"16c176f9-652f-4058-afdc-8b038248abfb","value":"Aurora_Ventas"},{"id":"8def1001-776c-4cb4-af49-b5a7d91b5f40","value":""},{"id":"51ed0bba-1b9c-42bb-b16b-9c1d92b60b5b","value":""},{"id":"de7114de-8bc0-456c-a090-c78a54a979b4","value":"Tipo Dispositivo"},{"id":"6e7271e5-19f8-4b02-b348-12b5580ea830","value":""},{"id":"5fde4f83-45df-4f80-9a03-dd2e1c9e69f3","value":""},{"id":"54958e71-6bf0-4ec2-9703-f0ca350ea51e","value":"Tareas Servidor"},{"id":"c8fc153d-b04f-497d-931f-4243c0635e4c","value":""},{"id":"39429879-b944-4ca7-8b28-813e8a9d1005","value":"Servicios Moviles"},{"id":"bf0a0b9f-ab06-4f06-a533-7852aeae119d","value":"Servicios Web"}]},{"containerId":"54958e71-6bf0-4ec2-9703-f0ca350ea51e","containerName":"Tareas Servidor","isSubprocess":true,"elements":[{"id":"810e8765-93b9-4fbc-b60e-2ac7976bdf29","value":"Web Connector"},{"id":"36a09cd3-740a-4bc2-88fa-55ae2daee9c0","value":""},{"id":"938e3318-6aaf-4591-a14f-651b666d4d9a","value":""},{"id":"1eb4f5e5-3481-4ba3-bdf7-a79cc104280e","value":""},{"id":"b502017f-287e-4470-bca3-a5c86583f5b2","value":"QuickBooks"},{"id":"248d1c49-c706-4263-813b-14ab69ed6794","value":""}]},{"containerId":"810e8765-93b9-4fbc-b60e-2ac7976bdf29","containerName":"Web Connector","isSubprocess":true,"elements":[{"id":"f118ca8d-9881-40b5-a529-7e361cbc4b68","value":""},{"id":"7a362c6d-33c5-41fd-9b3c-8aaffc9a762f","value":"Integrar QB con otras Aplicaciones"},{"id":"be6bb2f9-1c14-486d-9bf6-885b46f10466","value":"Funcionamento del Web Connector"},{"id":"3f06040e-c820-4dc4-8d07-9c07d192f120","value":""}]},{"containerId":"39429879-b944-4ca7-8b28-813e8a9d1005","containerName":"Servicios Moviles","isSubprocess":true,"elements":[{"id":"5e339e21-75f4-42b6-9de0-bce091f5eeb6","value":""},{"id":"276354c5-df96-44ad-9c7c-45086155d2a2","value":"Ingreso de Datos"},{"id":"349ba4d2-bf39-41bc-9886-8a98511c8afa","value":"Control de Datos"},{"id":"68df5e9d-5ed3-4a89-afad-ad38a53e739b","value":"Login de la Empresa"},{"id":"a07c1dbf-7ae7-4a14-b272-321e69bd887e","value":"Inicio del Dia"},{"id":"6e6849b3-c823-475a-a7e0-5668a9857a37","value":"Login del Usuario"},{"id":"1e534639-6a09-45b2-ac19-5a50dfc1b032","value":"En espera"}]},{"containerId":"276354c5-df96-44ad-9c7c-45086155d2a2","containerName":"Ingreso de Datos","isSubprocess":true,"elements":[]},{"containerId":"349ba4d2-bf39-41bc-9886-8a98511c8afa","containerName":"Control de Datos","isSubprocess":true,"elements":[]},{"containerId":"68df5e9d-5ed3-4a89-afad-ad38a53e739b","containerName":"Login de la Empresa","isSubprocess":true,"elements":[]},{"containerId":"6e6849b3-c823-475a-a7e0-5668a9857a37","containerName":"Login del Usuario","isSubprocess":true,"elements":[]},{"containerId":"bf0a0b9f-ab06-4f06-a533-7852aeae119d","containerName":"Servicios Web","isSubprocess":true,"elements":[{"id":"354e2f7a-a18c-4952-bfd8-cf1bf129e9a1","value":""},{"id":"d215b6cf-b999-43a5-8e86-aa6774cdb7bd","value":"Login del Usuario"},{"id":"76175745-c75e-445d-80fc-26f643f3f4b7","value":"WebSite Empresarial"},{"id":"af2b2507-58a9-4e09-b6fb-ee8c45e833a0","value":"En espera"},{"id":"a6b53a23-82fc-44ed-8cd6-f5fa2e28848c","value":"Asignacion de Usuario"},{"id":"93133059-b8c7-4720-aee2-b5f884d3f838","value":"Inicio del Dia"}]},{"containerId":"d215b6cf-b999-43a5-8e86-aa6774cdb7bd","containerName":"Login del Usuario","isSubprocess":true,"elements":[]},{"containerId":"a6b53a23-82fc-44ed-8cd6-f5fa2e28848c","containerName":"Asignacion de Usuario","isSubprocess":true,"elements":[]}]}