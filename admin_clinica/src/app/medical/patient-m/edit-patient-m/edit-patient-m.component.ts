import { Component, OnInit } from '@angular/core';
import { PatientMService } from '../service/patient-m.service';
import { ActivatedRoute } from '@angular/router';

@Component({
  selector: 'app-edit-patient-m',
  templateUrl: './edit-patient-m.component.html',
  styleUrls: ['./edit-patient-m.component.scss']
})
export class EditPatientMComponent implements OnInit {
  public selectedValue !: string  ;
  public name:string = '';
  public surname:string = '';
  public mobile:string = '';
  public email:string = '';

  public birth_date:string = '';
  public gender:number = 1;
  public education:string = '';
  public address:string = '';

  public antecedent_family:string = '';
  public antecedent_personal:string = '';
  public antecedent_allergic:string = '';

  public name_companion:string = '';
  public surname_companion:string = '';
  public mobile_companion:string = '';
  public relationship_companion:string = '';

  public name_responsible:string = '';
  public surname_responsible:string = '';
  public mobile_responsible:string = '';
  public relationship_responsible:string = '';

  public current_disease:string = '';

  public ta :number = 0;
  public temperatura :number = 0;
  public fc :number = 0;
  public fr :number = 0;
  public peso :number = 0;

  public n_document:any = null;

  public roles:any = [];

  public FILE_AVATAR:any;
  public IMAGEN_PREVIZUALIZA:any = 'assets/img/user-06.jpg';

  public text_success:string = '';
  public text_validation:string = '';

  public patient_id:any;
  constructor(
    public patientService: PatientMService,
    public activedRoute: ActivatedRoute,
  ) {
    
  }
  ngOnInit(): void {
    //Called after the constructor, initializing input properties, and the first call to ngOnChanges.
    //Add 'implements OnInit' to the class.
    // this.patientService.listConfig().subscribe((resp:any) => {
    //   console.log(resp);
    //   this.roles = resp.roles;
    // })
    this.activedRoute.params.subscribe((resp:any) => {
      this.patient_id = resp.id;
    })
    this.patientService.showPatient(this.patient_id).subscribe((resp:any) => {
      console.log(resp);

      this.name = resp.patient.name;
      this.surname = resp.patient.surname;
      this.email = resp.patient.email;
      this.mobile = resp.patient.mobile;
      this.n_document = resp.patient.n_document;
      this.birth_date = resp.patient.birth_date ? new Date(resp.patient.birth_date).toISOString() : '';
      this.gender = resp.patient.gender;
      this.education = resp.patient.education;
      this.address = resp.patient.address;
      this.antecedent_family = resp.patient.antecedent_family;
      this.antecedent_personal = resp.patient.antecedent_personal;
      this.antecedent_allergic = resp.patient.antecedent_allergic;

      this.name_companion = resp.patient.person.name_companion;
      this.surname_companion = resp.patient.person.surname_companion;
      this.mobile_companion = resp.patient.person.mobile_companion;
      this.relationship_companion = resp.patient.person.relationship_companion;
      this.name_responsible = resp.patient.person.name_responsible;
      this.surname_responsible = resp.patient.person.surname_responsible;
      this.mobile_responsible = resp.patient.person.mobile_responsible;
      this.relationship_responsible = resp.patient.person.relationship_responsible;

      this.current_disease = resp.patient.current_disease;
      this.ta = resp.patient.ta;
      this.temperatura = resp.patient.temperatura;
      this.fc = resp.patient.fc;
      this.fr = resp.patient.fr;
      this.peso = resp.patient.peso;
      this.IMAGEN_PREVIZUALIZA = resp.patient.avatar;
    })
  }

  save(){
    this.text_validation = '';
    const errorMessage = this.validateForm();
    if (errorMessage) {
      this.text_validation = errorMessage;
      return;
    }

    console.log(this.selectedValue);

    const formData = this.buildFormData();
    this.patientService.updatePatient(this.patient_id,formData).subscribe((resp:any) => {
      console.log(resp);

      if(resp.message == 403){
        this.text_validation = resp.message_text;
      }else{
        this.text_success = 'El paciente ha sido editado correctamente';
      }
    })
  }

  private validateForm(): string | null {
    if(!this.name || !this.n_document || !this.surname || !this.mobile){
      return "LOS CAMPOS SON NECESARIOS (Nombre,Apellido,° n de document, telefono)";
    }
    if(!this.name_companion || !this.surname_companion){
      return "EL NOPMBRE Y APELLIDO DEL ACOMPAÑANTE ES OBLIGATORIO (Nombre,Apellido)";
    }
    if(!this.ta || !this.temperatura || !this.fc || !this.fr || !this.peso){
      return "LOS SIGNOS VITALES SON OBLIGATORIO";
    }
    return null;
  }

  private buildFormData(): FormData {
    const formData = new FormData();
    formData.append("name",this.name);
    formData.append("surname",this.surname);
    this.appendIfPresent(formData, "email", this.email);
    formData.append("mobile",this.mobile);
    formData.append("n_document",this.n_document);
    this.appendIfPresent(formData, "birth_date", this.birth_date);
    formData.append("gender",this.gender+"");
    this.appendIfPresent(formData, "education", this.education);
    this.appendIfPresent(formData, "address", this.address);
    this.appendIfPresent(formData, "imagen", this.FILE_AVATAR);
    this.appendIfPresent(formData, "antecedent_family", this.antecedent_family);
    this.appendIfPresent(formData, "antecedent_personal", this.antecedent_personal);
    this.appendIfPresent(formData, "antecedent_allergic", this.antecedent_allergic);

    formData.append("name_companion",this.name_companion);
    formData.append("surname_companion",this.surname_companion);
    this.appendIfPresent(formData, "mobile_companion", this.mobile_companion);
    this.appendIfPresent(formData, "relationship_companion", this.relationship_companion);
    this.appendIfPresent(formData, "name_responsible", this.name_responsible);
    this.appendIfPresent(formData, "surname_responsible", this.surname_responsible);
    this.appendIfPresent(formData, "mobile_responsible", this.mobile_responsible);
    this.appendIfPresent(formData, "relationship_responsible", this.relationship_responsible);
    this.appendIfPresent(formData, "current_disease", this.current_disease);

    formData.append("ta",this.ta+"");
    formData.append("temperatura",this.temperatura+"");
    formData.append("fc",this.fc+"");
    formData.append("fr",this.fr+"");
    formData.append("peso",this.peso+"");
    return formData;
  }

  private appendIfPresent(formData: FormData, key: string, value: any): void {
    if (value !== null && value !== undefined && value !== '') {
      formData.append(key, value);
    }
  }

  loadFile($event:any){
    if($event.target.files[0].type.indexOf("image") < 0){
      // alert("SOLAMENTE PUEDEN SER ARCHIVOS DE TIPO IMAGEN");
      this.text_validation = "SOLAMENTE PUEDEN SER ARCHIVOS DE TIPO IMAGEN";
      return;
    }
    this.text_validation = '';
    this.FILE_AVATAR = $event.target.files[0];
    let reader = new FileReader();
    reader.readAsDataURL(this.FILE_AVATAR);
    reader.onloadend = () => this.IMAGEN_PREVIZUALIZA = reader.result;
  }
  
}
