<template>
    <form>

        <div class="row">
            <div class="col-sm-12">
                <div class="form-group">
                    <label >From Blog</label>
                    <select v-model="blog_type" @change="handleBlogtypeChange">
                        <option value="0">All Blog</option>
                        <option value="1">Name</option>
                    </select>
                </div>
                <div class="form-group" v-if="blog_type==1">
                    <label >Blog name:</label>
                    <input type="text" class="form-control" v-model="blogname">
                    <div v-if="hasError && errors.blogname" class="error">{{errors.blogname[0]}}</div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-6">
                <div class="form-group">
                    <label >Tag:</label>
                    <input type="text" name="tag" class="form-control"  v-model="tag">

                    <div v-if="hasError && errors.tag" class="error" v-text="errors.tag[0]"></div>

                </div>
            </div>

            <div class="col-sm-6"  v-if="blog_type == 1">
                <div class="form-group">
                    <label >Post Type:</label>
                    <select v-model="post_type" id="" class="form-control" @change="handlePosttypeChange" >
                        <option :value="type" v-for="type in list_post_type" v-text="type"></option>
                    </select>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-6" v-if="blog_type == 1" >
                <div class="form-group">
                    <label >Start:</label>
                    <input type="text" class="form-control"  v-model="start">
                </div>
            </div>

            <div class="col-sm-6">
                <div class="form-group">
                    <label >Limit:</label>
                    <input type="text" class="form-control"  v-model="limit">
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
                Post Not Found :)
            </div>


            <div v-if="result.total > 0 && result.post_type=='photo'">
                <a  :href="result.zip_link" class="btn btn-default"><i class="fa fa-picture-o"></i> + <i class="fa fa-file-excel-o"></i> Download (zip)</a>
            </div>
            <div style="padding: 10px;">

                <div v-if="post_type=='photo'">
                    <img width="100" class="thumbnail" :src="url.image_url"  alt="" v-for="url in result.images">
                </div>
                <div v-if="post_type=='text'  && blog_type == 1">
                    <div v-for="post in result.posts" class="line-item">
                        <p>title : <a :href="post.post_url" v-text="post.title">view post</a></p>
                        <strong>content :</strong>
                        <p>blogname : {{post.blog_name}}</p>
                        <p v-html="post.body"></p>
                        <div>
                            <span class="tag" v-for="tag in post.tags">#{{tag}}</span>
                        </div>
                    </div>
                </div>

                <div v-if="post_type=='audio' && blog_type == 1">
                    <div v-for="post in result.posts" class="line-item">
                        <div class="row">
                            <div class="col-sm-6">

                                <p>  track_name : {{post.track_name}}</p>
                                <p>  artist : {{post.artist}}</p>
                                <p>  album : {{post.album}}</p>
                                <div>  caption : <div v-html="post.caption"></div>
                                </div>

                                <div>
                                    <span class="tag" v-for="tag in post.tags">#{{tag}}</span>
                                </div>

                                <p><a :href="post.post_url" class="btn btn-default" target="_blank">View Detail</a></p>
                            </div>
                            <div class="col-sm-6">
                                <div class="player-audio" v-html="post.player">

                                </div>
                            </div>
                        </div>
                    </div>
                </div>


            </div>


        </div>

    </form>
</template>



<script>

    import axios from 'axios'


    const POST_TYPE = [
        'photo',
        'text',
        'audio',
       /* 'video',
        'chat'*/
    ]

    export default {
        
        data(){
            return {
                blog_type:0,
                list_post_type:POST_TYPE,
                preload:false,
                post_type:'photo',
                errors:{},
                result:{},
                tag:'',
                blogname:'sonlexus',
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

        mounted(){
            console.log(this.$data.limit);

        },

        methods:{

            handleBlogtypeChange(){

                if(this.blog_type == 0){
                    this.post_type = 'photo'
                }

                this.errors = {}
                this.result = {}
            },
            handlePosttypeChange(){
                this.result = {}
            },
            handleSubmit(){

                let {blogname,tag,limit,blog_type,post_type}  = this;





                this.preload = true;
                this.errors = {};
                this.result = {};

                axios.post('/tumblr', {blogname,tag,limit,blog_type,post_type})
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

<style>
    iframe.spotify_audio_player{
        width: 100% !important;
        height: 300px !important;
    }
</style>
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


    .line-item{
        margin-bottom: 10px;
        background: #f5f8fa;
        padding: 20px;
        border-radius: 3px;
        box-shadow: 1px 1px 1px #ddd;
    }
    .tag{
        background: #d1230d;
        border-radius: 3px;
        margin: 0 5px 5px 0;
        padding: 3px 5px;
        border: 0;
        color: white;
    }
</style>