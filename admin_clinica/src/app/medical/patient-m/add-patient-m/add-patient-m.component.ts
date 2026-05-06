import { Component } from '@angular/core';
import { PatientMService } from '../service/patient-m.service';

@Component({
  selector: 'app-add-patient-m',
  templateUrl: './add-patient-m.component.html',
  styleUrls: ['./add-patient-m.component.scss']
})
export class AddPatientMComponent {
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
  constructor(
    public patientService: PatientMService,
  ) {
    
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
    this.patientService.registerPatient(formData).subscribe((resp:any) => {
      console.log(resp);

      if(resp.message == 403){
        this.text_validation = resp.message_text;
      }else{
        this.text_success = 'El paciente ha sido registrado correctamente';
        this.resetForm();
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

  private resetForm(): void {
    this.name = '';
    this.surname = '';
    this.email  = '';
    this.mobile  = '';
    this.birth_date  = '';
    this.gender  = 1;
    this.education  = '';
    this.n_document  = '';
    this.antecedent_family  = '';
    this.antecedent_personal  = '';
    this.antecedent_allergic  = '';
    this.name_companion  = '';
    this.surname_companion  = '';
    this.mobile_companion  = '';
    this.relationship_companion  = '';
    this.name_responsible  = '';
    this.surname_responsible  = '';
    this.mobile_responsible  = '';
    this.relationship_responsible  = '';
    this.current_disease  = '';
    this.ta = 0;
    this.temperatura = 0;
    this.fc = 0;
    this.fr = 0;
    this.peso = 0;
    this.address  = '';
    this.selectedValue  = '';
    this.FILE_AVATAR = null;
    this.IMAGEN_PREVIZUALIZA = null;
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
