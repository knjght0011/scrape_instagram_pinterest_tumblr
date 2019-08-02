<template>
    <form>

        <div class="row">
            <div class="col-sm-6">
                <div class="form-group">
                    <label >Username:</label>
                    <input type="text" name="username" class="form-control"  v-model="username">

                    <div v-if="hasError && errors.username" class="error" v-text="errors.username[0]"></div>

                </div>
            </div>

            <div class="col-sm-6">
                <div class="form-group">
                    <label >Board:</label>
                    <input type="text" name="board" class="form-control"  v-model="board">

                    <div v-if="hasError && errors.board" class="error" v-text="errors.board[0]"></div>

                </div>
            </div>


        </div>

        <div class="row">
            <div class="col-sm-12">
                <button

                        @click.prevent="handleSubmit()"
                        type="submit"
                        :class="{'disabled':preload,'btn btn-default':true}"

                >
                    Scrape</button>
                <img src="/imgs/preload.gif" v-if="preload"  alt="">
            </div>
        </div>

        <div class="result" v-if="success">
            <h3  class="text-center">Result</h3>

            <div class="text-center" v-if="result.total == 0">
                Image Not Found :)
            </div>

            <div v-if="result.total">
                <a  :href="result.zip_link" class="btn btn-default"><i class="fa fa-picture-o"></i> + <i class="fa fa-file-excel-o"></i> Download (zip)</a>
            </div>
            <div style="padding: 10px;">
                <img width="100" class="thumbnail" :src="url.image_url"  alt="" v-for="url in result.images">
            </div>


        </div>

    </form>
</template>



<script>

    import axios from 'axios'


    export default {
        
        data(){
            return {
                preload:false,
                errors:{},
                result:{},
                username:'grainedit',
                board:'design-numbers',
                start:0,
                limit:10
            }
        },

        computed:{


            success(){
                return Object.keys(this.result).length >0
            },

            hasError(){
                return Object.keys(this.errors).length >0
            }

        },



        methods:{

            handleSubmit(){

                let {username,board}  = this;

                this.preload = true;
                this.errors = {};
                this.result = {};

                axios.post('/pinterest', {username,board})
                .then( (response)=> {


                    this.result = response.data
                    this.preload = false;

                })
                .catch( (res) => {

                    let err = res.response

                    Object.keys(err.data.message).forEach(key=>{
                        this.$set(this.errors, key, err.data.message[key])
                    })

                    this.preload = false;
                });
            }
        }

    }
</script>

<style scoped>
    .error{
        color: red;
    }
    .result h3{
        margin: 0;
        font-size: 16px;
        font-weight: bold;
        border-bottom: 1px solid #ddd;
        margin-bottom: 20px;
        line-height: 30px;
    }
    .result .btn{
        margin: 10px;
    }
    .result{
        padding: 10px 0;
        border: 1px solid #ccd6cc;
        margin: 20px 0;
        background: white;;
        overflow: hidden;
    }

    .thumbnail {
        width: 100px;
        float: left;
        height: 100px;
        margin: 0 10px 10px 0;
        object-fit: cover;
    }

</style>