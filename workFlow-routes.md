## WorkFlow - Routes

Create Process
==============

Route : /api/processes
Method : POST
Body:  formData : { name: String }
Response : processCreated { name: string, id: int }

Create State
==============

Route : /api/states
Method : POST
Body:  row : [{ name: String, process_id: int }, { name: String, process_id: int }, { name: String, process_id: int }, ...]
Response : { 'statesCreated' : [{ name: String, process_id: int, id: int }, { name: String, process_id: int, id:int }, { name: String, process_id: int, id: int }, ...]}



Create Transitions
==============

Route : /api/transitions
Method : POST
Body:  row : [{ previous_state: int, next_state: int, action: int }, { previous_state: int, next_state: int, action: int }, { previous_state: int, next_state: int, action: int }, ...]
Response : { 'transitionsCreated' :[{ previous_state: int, next_state: int, action: int, id: int }, { previous_state: int, next_state: int, action: int, id: int }, { previous_state: int, next_state: int, action: int, id: int }, ...] }

Create Profile
==============

Route : /api/profiles
Method : POST
Body:  formData : { name: String, user_id: int }
Response : profileCreated { name: string,user_id: int, id: int }

Create Action
==============

Route : /api/actions
Method : POST
Body:  formData : { name: String }
Response : profileCreated { name: string, id: int }
