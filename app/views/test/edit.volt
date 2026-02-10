  <div class="page-title"><a href="#" class="backlink"><i class="fa fa-arrow-circle-o-up"></i></a> <h3>Содержимое заявки</h3></div>
  
  {{ flash.output() }}
  
  <div class="row">
    <div class="col-sm-12">
      <div class="panel panel-default panel-primary">
        <div class="panel-heading">
          <div class="row">
            <div class="col-sm-3">Содержимое заявки</div>
            <div class="col-sm-9 text-right"></div>
          </div>
        </div>
        <div class="panel-body">
          <div class="table-responsive">
          <table class="table table-striped table-bordered" id="exaMain">
            <thead>
              <tr class="">
                <th>ID</th>
                <th>Вес, кг</th>
                <th>Дата импорта</th>
                <th>Страна</th>
                <th>Код ТН ВЭД</th>
                <th>Способ расчета</th>
                <th>Сумма, тенге</th>
                <th>Документы</th>
              </tr>
            </thead>
            <tbody>
              <tr class="">
                <td class="v-align-middle">1</td>
                <td class="v-align-middle">2600,00</td>
                <td class="v-align-middle">12.03.2017</td>
                <td class="v-align-middle"></td>
                <td class="v-align-middle">3504820000</td>
                <td class="v-align-middle"></td>
                <td class="v-align-middle">3500,00</td>
                <td class="v-align-middle">
                    <button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-target="#myModal"><i class="fa fa-file"></i></button>
                </td>
              </tr>
              <tr class="">
                <td class="v-align-middle">2</td>
                <td class="v-align-middle">750,00</td>
                <td class="v-align-middle">12.03.2017</td>
                <td class="v-align-middle"></td>
                <td class="v-align-middle">46548798</td>
                <td class="v-align-middle"></td>
                <td class="v-align-middle">140,00</td>
                <td class="v-align-middle">
                    <button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-target="#myModal"><i class="fa fa-file"></i></button>
                </td>
              </tr>
              <tr class="">
                <td class="v-align-middle">3</td>
                <td class="v-align-middle">100,00</td>
                <td class="v-align-middle">12.03.2017</td>
                <td class="v-align-middle"></td>
                <td class="v-align-middle">450789654</td>
                <td class="v-align-middle"></td>
                <td class="v-align-middle">500,00</td>
                <td class="v-align-middle">
                    <button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-target="#myModal"><i class="fa fa-file"></i></button>
                </td>
              </tr>
              <tr class="">
                <td class="v-align-middle">4</td>
                <td class="v-align-middle">50,00</td>
                <td class="v-align-middle">17.03.2017</td>
                <td class="v-align-middle"></td>
                <td class="v-align-middle">001300205</td>
                <td class="v-align-middle"></td>
                <td class="v-align-middle">350,00</td>
                <td class="v-align-middle">
                    <button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-target="#myModal"><i class="fa fa-file"></i></button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        </div>
      </div>
    </div>
  </div>
  
  <div class="modal fade" tabindex="-1" role="dialog" id="myModal">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          <h4 class="modal-title">Документы к товару</h4>
        </div>
        <div class="modal-body">
            <div class="upload-drop-zone" id="drop-zone">
                Бросьте файлы сюда
            </div>    
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">Закрыть</button>
          <button type="button" class="btn btn-primary">Сохранить</button>
        </div>
      </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
  </div><!-- /.modal -->